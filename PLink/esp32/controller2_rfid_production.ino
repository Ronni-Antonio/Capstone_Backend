#include <SPI.h>
#include <MFRC522.h>
#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <Preferences.h>

#define SS_PIN   5
#define RST_PIN  22

MFRC522 mfrc522(SS_PIN, RST_PIN);
Preferences preferences;

// ============================================================
// PLink Controller 2
// RFID assignment + identification + reward confirmation
// Outbound HTTPS only; no WebServer or router port-forwarding.
// ============================================================

static const char* API_BASE = "https://plink-api.barabesta.is/api";
static const char* CONTROLLER_CODE = "controller-2";

// IMPORTANT: must exactly match IOT_CONTROLLER_2_KEY in Laravel .env.
// Replace this before flashing; do not commit the real production key.
static const char* DEVICE_KEY = "4ba00b9849eaeb6fdce45fb85e95f4326166014c50ba10d3843a32e6d72f4ab1";

// Used only on first boot / when NVS has no saved credentials yet.
static const char* DEFAULT_WIFI_SSID = "GlobeAtHome_38756_2.4";
static const char* DEFAULT_WIFI_PASSWORD = "Shinchan215";

static const unsigned long COMMAND_POLL_INTERVAL_MS = 1500;
static const unsigned long CONFIG_POLL_INTERVAL_MS  = 60000;
static const unsigned long WIFI_RETRY_INTERVAL_MS   = 10000;
static const unsigned long RFID_COOLDOWN_MS         = 1500;
static const unsigned long COMMAND_WAIT_TIMEOUT_MS  = 180000;

unsigned long lastCommandPoll = 0;
unsigned long lastConfigPoll = 0;
unsigned long lastWifiRetry = 0;
unsigned long lastCardRead = 0;
unsigned long currentCommandStartedAt = 0;

enum ControllerMode {
  MODE_IDLE,
  MODE_ASSIGN_CARD,
  MODE_REDEMPTION
};

ControllerMode currentMode = MODE_IDLE;
long currentCommandId = 0;
String pendingStudentId = "";
String pendingRewardId = "";

String activeSsid;
String activePassword;
int appliedConfigVersion = 0;
int lastAttemptedConfigVersion = 0;

// ============================================================
// General helpers
// ============================================================

bool isHttpSuccess(int status) {
  return status >= 200 && status < 300;
}

void addDeviceHeaders(HTTPClient& http, bool jsonBody = false) {
  http.addHeader("Accept", "application/json");
  http.addHeader("X-Device-Key", DEVICE_KEY);
  if (jsonBody) {
    http.addHeader("Content-Type", "application/json");
  }
}

String readCardUid() {
  String uid = "";
  for (byte i = 0; i < mfrc522.uid.size; i++) {
    if (mfrc522.uid.uidByte[i] < 0x10) uid += "0";
    uid += String(mfrc522.uid.uidByte[i], HEX);
  }
  uid.toUpperCase();
  return uid;
}

void clearCurrentCommand() {
  currentMode = MODE_IDLE;
  currentCommandId = 0;
  pendingStudentId = "";
  pendingRewardId = "";
  currentCommandStartedAt = 0;
}

// ============================================================
// Wi-Fi Preferences / connection
// ============================================================

void loadWifiFromPreferences() {
  preferences.begin("plink_wifi", true);
  activeSsid = preferences.getString("ssid", DEFAULT_WIFI_SSID);
  activePassword = preferences.getString("password", DEFAULT_WIFI_PASSWORD);
  appliedConfigVersion = preferences.getInt("applied_ver", 0);
  lastAttemptedConfigVersion = preferences.getInt("attempt_ver", 0);
  preferences.end();
}

bool connectWifi(const String& ssid, const String& password, unsigned long timeoutMs = 20000) {
  if (ssid.length() == 0) return false;

  Serial.printf("Connecting to Wi-Fi: %s\n", ssid.c_str());
  WiFi.mode(WIFI_STA);
  WiFi.disconnect(true, false);
  delay(250);
  WiFi.begin(ssid.c_str(), password.c_str());

  const unsigned long started = millis();
  while (WiFi.status() != WL_CONNECTED && millis() - started < timeoutMs) {
    delay(400);
    Serial.print(".");
  }
  Serial.println();

  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("Wi-Fi connected.");
    Serial.print("IP: ");
    Serial.println(WiFi.localIP());
    Serial.print("RSSI: ");
    Serial.println(WiFi.RSSI());
    return true;
  }

  Serial.println("Wi-Fi connection failed.");
  return false;
}

void saveNewWifiCredentials(const String& newSsid, const String& newPassword, int version) {
  preferences.begin("plink_wifi", false);
  preferences.putString("backup_ssid", activeSsid);
  preferences.putString("backup_pass", activePassword);
  preferences.putString("ssid", newSsid);
  preferences.putString("password", newPassword);
  preferences.putInt("attempt_ver", version);
  preferences.end();
}

void restoreBackupWifi() {
  preferences.begin("plink_wifi", false);
  String backupSsid = preferences.getString("backup_ssid", "");
  String backupPass = preferences.getString("backup_pass", "");

  if (backupSsid.length() > 0) {
    preferences.putString("ssid", backupSsid);
    preferences.putString("password", backupPass);
    activeSsid = backupSsid;
    activePassword = backupPass;
  }
  preferences.end();
}

// ============================================================
// Remote Wi-Fi config (existing System Settings feature)
// ============================================================

bool acknowledgeConfigVersion(int version) {
  if (WiFi.status() != WL_CONNECTED) return false;

  WiFiClientSecure client;
  client.setInsecure(); // Prototype only. Use CA validation for final production.
  HTTPClient http;

  String url = String(API_BASE) + "/iot/device-config/" + CONTROLLER_CODE + "/ack";
  if (!http.begin(client, url)) return false;
  addDeviceHeaders(http, true);

  JsonDocument doc;
  doc["config_version"] = version;
  String body;
  serializeJson(doc, body);

  int code = http.POST(body);
  String response = http.getString();
  http.end();

  Serial.printf("Config ACK HTTP %d: %s\n", code, response.c_str());

  if (isHttpSuccess(code)) {
    preferences.begin("plink_wifi", false);
    preferences.putInt("applied_ver", version);
    preferences.end();
    appliedConfigVersion = version;
    return true;
  }

  return false;
}

void checkRemoteWifiConfig() {
  if (WiFi.status() != WL_CONNECTED) return;

  WiFiClientSecure client;
  client.setInsecure();
  HTTPClient http;

  String url = String(API_BASE) + "/iot/device-config/" + CONTROLLER_CODE;
  if (!http.begin(client, url)) return;
  addDeviceHeaders(http);

  int code = http.GET();
  String response = http.getString();
  http.end();

  if (code != 200) {
    Serial.printf("Config poll HTTP %d: %s\n", code, response.c_str());
    return;
  }

  JsonDocument doc;
  DeserializationError error = deserializeJson(doc, response);
  if (error) {
    Serial.printf("Config JSON error: %s\n", error.c_str());
    return;
  }

  int desiredVersion = doc["config_version"] | 0;
  int backendAppliedVersion = doc["applied_version"] | 0;
  bool restartRequired = doc["restart_required"] | false;

  if (!restartRequired || desiredVersion <= backendAppliedVersion) return;

  // If this exact version was already attempted and failed, stay on the
  // fallback Wi-Fi and wait for the admin to save a newer version.
  if (desiredVersion == lastAttemptedConfigVersion && desiredVersion > appliedConfigVersion) {
    return;
  }

  String newSsid = doc["wifi_ssid"] | "";
  String newPassword = doc["wifi_password"] | "";

  if (newSsid.length() == 0) {
    Serial.println("Remote Wi-Fi config ignored: empty SSID.");
    return;
  }

  Serial.printf("New Wi-Fi config version %d received. Restarting...\n", desiredVersion);
  saveNewWifiCredentials(newSsid, newPassword, desiredVersion);
  delay(500);
  ESP.restart();
}

void verifyNewWifiOrFallback() {
  loadWifiFromPreferences();

  if (connectWifi(activeSsid, activePassword)) {
    if (lastAttemptedConfigVersion > appliedConfigVersion) {
      acknowledgeConfigVersion(lastAttemptedConfigVersion);
    }
    return;
  }

  // If newly configured Wi-Fi is invalid, restore previous working credentials.
  Serial.println("Trying backup Wi-Fi credentials...");
  restoreBackupWifi();
  connectWifi(activeSsid, activePassword);
}

// ============================================================
// Backend command queue
// ============================================================

void acknowledgeCommand(long commandId, const char* status, const String& message) {
  if (WiFi.status() != WL_CONNECTED || commandId <= 0) return;

  WiFiClientSecure client;
  client.setInsecure();
  HTTPClient http;

  String url = String(API_BASE)
    + "/iot/controller-commands/"
    + CONTROLLER_CODE
    + "/"
    + String(commandId)
    + "/ack";

  if (!http.begin(client, url)) return;
  addDeviceHeaders(http, true);

  JsonDocument doc;
  doc["status"] = status;
  doc["message"] = message;
  String body;
  serializeJson(doc, body);

  int code = http.POST(body);
  String response = http.getString();
  http.end();

  Serial.printf("Command ACK HTTP %d: %s\n", code, response.c_str());
}

void pollNextCommand() {
  if (WiFi.status() != WL_CONNECTED || currentMode != MODE_IDLE) return;

  WiFiClientSecure client;
  client.setInsecure();
  HTTPClient http;

  String url = String(API_BASE)
    + "/iot/controller-commands/"
    + CONTROLLER_CODE
    + "/next";

  if (!http.begin(client, url)) return;
  addDeviceHeaders(http);

  int code = http.GET();
  String response = http.getString();
  http.end();

  if (code != 200) {
    Serial.printf("Command poll HTTP %d: %s\n", code, response.c_str());
    return;
  }

  JsonDocument doc;
  DeserializationError error = deserializeJson(doc, response);
  if (error || !(doc["has_command"] | false)) return;

  JsonObject command = doc["command"];
  currentCommandId = command["command_id"] | 0;
  String commandType = command["command_type"] | "";
  JsonObject payload = command["payload"];
  currentCommandStartedAt = millis();

  if (commandType == "assign_card") {
    pendingStudentId = payload["student_id"].as<String>();
    currentMode = MODE_ASSIGN_CARD;
    Serial.printf("\nASSIGN CARD #%ld for student %s. Tap card now.\n",
                  currentCommandId, pendingStudentId.c_str());
    return;
  }

  if (commandType == "confirm_redemption") {
    pendingStudentId = payload["student_id"].as<String>();
    pendingRewardId = payload["reward_id"].as<String>();
    currentMode = MODE_REDEMPTION;
    Serial.printf("\nREDEMPTION #%ld: student %s, reward %s. Tap card now.\n",
                  currentCommandId, pendingStudentId.c_str(), pendingRewardId.c_str());
    return;
  }

  acknowledgeCommand(currentCommandId, "failed", "Unsupported command type: " + commandType);
  clearCurrentCommand();
}

void checkCommandTimeout() {
  if (currentMode == MODE_IDLE || currentCommandStartedAt == 0) return;

  if (millis() - currentCommandStartedAt >= COMMAND_WAIT_TIMEOUT_MS) {
    Serial.println("Controller command timed out waiting for RFID tap.");
    acknowledgeCommand(currentCommandId, "failed", "Timed out waiting for RFID card tap.");
    clearCurrentCommand();
  }
}

// ============================================================
// RFID operations
// ============================================================

void assignCard(const String& cardUid) {
  WiFiClientSecure client;
  client.setInsecure();
  HTTPClient http;

  String url = String(API_BASE) + "/students/assign-card";
  if (!http.begin(client, url)) return;
  addDeviceHeaders(http, true);

  JsonDocument doc;
  doc["student_id"] = pendingStudentId;
  doc["card_uid"] = cardUid;
  String body;
  serializeJson(doc, body);

  int code = http.POST(body);
  String response = http.getString();
  http.end();

  Serial.printf("Assign card HTTP %d: %s\n", code, response.c_str());

  if (isHttpSuccess(code)) {
    acknowledgeCommand(currentCommandId, "completed", "RFID card assigned successfully.");
    clearCurrentCommand();
  } else if (code > 0) {
    acknowledgeCommand(currentCommandId, "failed", response);
    clearCurrentCommand();
  }
}

void confirmRedemption(const String& cardUid) {
  WiFiClientSecure client;
  client.setInsecure();
  HTTPClient http;

  String url = String(API_BASE) + "/redemptions";
  if (!http.begin(client, url)) return;
  addDeviceHeaders(http, true);

  JsonDocument doc;
  doc["student_id"] = pendingStudentId;
  doc["reward_id"] = pendingRewardId;
  doc["card_uid"] = cardUid;
  String body;
  serializeJson(doc, body);

  int code = http.POST(body);
  String response = http.getString();
  http.end();

  Serial.printf("Redemption HTTP %d: %s\n", code, response.c_str());

  if (isHttpSuccess(code)) {
    acknowledgeCommand(currentCommandId, "completed", "Reward redemption completed successfully.");
    clearCurrentCommand();
  } else if (code > 0) {
    acknowledgeCommand(currentCommandId, "failed", response);
    clearCurrentCommand();
  }
}

void identifyStudent(const String& cardUid) {
  WiFiClientSecure client;
  client.setInsecure();
  HTTPClient http;

  String url = String(API_BASE) + "/students/identify-card";
  if (!http.begin(client, url)) return;
  addDeviceHeaders(http, true);

  JsonDocument doc;
  doc["card_uid"] = cardUid;
  String body;
  serializeJson(doc, body);

  int code = http.POST(body);
  String response = http.getString();
  http.end();

  if (isHttpSuccess(code)) {
    Serial.print("Student identified: ");
    Serial.println(response);
    // Laravel caches this scan briefly; the Rewards page sees it through
    // GET /students/active-scan-session.
  } else {
    Serial.printf("Identify HTTP %d: %s\n", code, response.c_str());
  }
}

void handleRfidTap() {
  if (!mfrc522.PICC_IsNewCardPresent() || !mfrc522.PICC_ReadCardSerial()) return;

  if (millis() - lastCardRead < RFID_COOLDOWN_MS) {
    mfrc522.PICC_HaltA();
    return;
  }
  lastCardRead = millis();

  String cardUid = readCardUid();
  Serial.printf("\nRFID card detected: %s\n", cardUid.c_str());

  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("Cannot process RFID: Wi-Fi is offline.");
    mfrc522.PICC_HaltA();
    return;
  }

  switch (currentMode) {
    case MODE_ASSIGN_CARD:
      assignCard(cardUid);
      break;

    case MODE_REDEMPTION:
      confirmRedemption(cardUid);
      break;

    case MODE_IDLE:
    default:
      identifyStudent(cardUid);
      break;
  }

  mfrc522.PICC_HaltA();
  mfrc522.PCD_StopCrypto1();
}

// ============================================================
// Arduino lifecycle
// ============================================================

void setup() {
  Serial.begin(115200);
  delay(500);

  SPI.begin();
  mfrc522.PCD_Init();
  Serial.println("RC522 initialized.");

  verifyNewWifiOrFallback();

  // Cause first polls to happen immediately after startup.
  lastCommandPoll = millis() - COMMAND_POLL_INTERVAL_MS;
  lastConfigPoll = millis() - CONFIG_POLL_INTERVAL_MS;
}

void loop() {
  unsigned long now = millis();

  if (WiFi.status() != WL_CONNECTED && now - lastWifiRetry >= WIFI_RETRY_INTERVAL_MS) {
    lastWifiRetry = now;
    connectWifi(activeSsid, activePassword, 8000);
  }

  if (WiFi.status() == WL_CONNECTED) {
    if (now - lastCommandPoll >= COMMAND_POLL_INTERVAL_MS) {
      lastCommandPoll = now;
      pollNextCommand();
    }

    if (now - lastConfigPoll >= CONFIG_POLL_INTERVAL_MS) {
      lastConfigPoll = now;
      checkRemoteWifiConfig();
    }
  }

  checkCommandTimeout();
  handleRfidTap();
  delay(25);
}
