#include <Arduino.h>
#include "esp_camera.h"

#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <Preferences.h>
#include <SPI.h>
#include <MFRC522.h>

#include "board_config.h"

// ============================================================
// CONTROLLER 1 IDENTITY
// ============================================================

static const char* CONTROLLER_CODE = "controller-1";

static const char* DEVICE_KEY =
    "5ef78560f7abd4ef8a55d00453ef8cc2a86ed595dbc182dd3239cc7da01b6d92";

static const char* API_BASE =
    "https://plink-api.barabesta.is/api";

static const char* CLASSIFICATION_URL =
    "https://plink-api.barabesta.is/api/iot/classify";

static const char* IDENTIFY_CARD_URL =
    "https://plink-api.barabesta.is/api/students/identify-card";

// ============================================================
// FALLBACK WI-FI
// ============================================================

static const char* FALLBACK_SSID =
    "GlobeAtHome_38756_2.4";

static const char* FALLBACK_PASSWORD =
    "Shinchan215";

// ============================================================
// IR SENSOR
// ============================================================

#define IR_SENSOR_PIN 42
#define IR_DETECTED_STATE LOW

const unsigned long CAPTURE_DELAY_MS = 500;
const unsigned long CAPTURE_COOLDOWN_MS = 3000;

// ============================================================
// RFID RC522
// Wiring:
//   SDA / SS -> GPIO41
//   SCK      -> GPIO40
//   MOSI     -> GPIO38
//   MISO     -> GPIO39
//   RST      -> GPIO2
//   3.3V     -> 3.3V
//   GND      -> GND
//   IRQ      -> not connected
// ============================================================

#define RFID_SS_PIN   41
#define RFID_SCK_PIN  40
#define RFID_MOSI_PIN 38
#define RFID_MISO_PIN 39
#define RFID_RST_PIN   2

const unsigned long RFID_SCAN_COOLDOWN_MS = 1500;

MFRC522 rfid(RFID_SS_PIN, RFID_RST_PIN);
unsigned long lastRfidScanTime = 0;
String lastRfidUid = "";

// ============================================================
// REMOTE CONFIG
// ============================================================

const unsigned long CONFIG_CHECK_INTERVAL_MS = 60000;

unsigned long lastConfigCheck = 0;

// ============================================================
// GLOBAL STATE
// ============================================================

Preferences preferences;

bool objectPreviouslyDetected = false;
bool classificationInProgress = false;
unsigned long lastCaptureTime = 0;

String lastClassification = "";
String lastCompartment = "";
int pendingSessionPoints = 0;
int pendingSessionItems = 0;

void startCameraServer();
void setupLedFlash();

// ============================================================
// HTTPS HELPER
// ============================================================

bool beginSecureRequest(
    WiFiClientSecure& client,
    HTTPClient& http,
    const String& url
) {
    // Development/capstone testing:
    // encrypted HTTPS is used, but the server certificate is not pinned.
    // For the final hardened build, replace setInsecure() with the
    // appropriate CA certificate.
    client.setInsecure();

    http.setConnectTimeout(10000);
    http.setTimeout(30000);

    return http.begin(client, url);
}

bool isHttpSuccess(int code) {
    return code >= 200 && code < 300;
}

// ============================================================
// WI-FI / NVS
// ============================================================

String loadStoredString(
    const char* key,
    const char* fallback
) {
    preferences.begin("plink-wifi", true);
    String value = preferences.getString(key, fallback);
    preferences.end();
    return value;
}

void saveWiFiCredentials(
    const String& ssid,
    const String& password
) {
    preferences.begin("plink-wifi", false);
    preferences.putString("ssid", ssid);
    preferences.putString("password", password);
    preferences.end();
}

void connectToWiFi() {
    String ssid =
        loadStoredString("ssid", FALLBACK_SSID);

    String password =
        loadStoredString("password", FALLBACK_PASSWORD);

    WiFi.mode(WIFI_STA);
    WiFi.setSleep(false);

    WiFi.begin(
        ssid.c_str(),
        password.c_str()
    );

    Serial.print("Connecting to Wi-Fi");

    unsigned long started = millis();

    while (
        WiFi.status() != WL_CONNECTED &&
        millis() - started < 30000
    ) {
        delay(500);
        Serial.print(".");
    }

    Serial.println();

    if (WiFi.status() != WL_CONNECTED) {
        Serial.println("Wi-Fi connection timed out.");
        return;
    }

    Serial.println("Wi-Fi connected.");

    Serial.print("SSID: ");
    Serial.println(WiFi.SSID());

    Serial.print("ESP32 IP: ");
    Serial.println(WiFi.localIP());

    Serial.print("RSSI: ");
    Serial.print(WiFi.RSSI());
    Serial.println(" dBm");
}

void maintainWiFi() {
    if (WiFi.status() == WL_CONNECTED) {
        return;
    }

    static unsigned long lastReconnectAttempt = 0;

    if (millis() - lastReconnectAttempt < 10000) {
        return;
    }

    lastReconnectAttempt = millis();

    Serial.println("Wi-Fi disconnected. Reconnecting...");
    WiFi.disconnect();
    connectToWiFi();
}

// ============================================================
// REMOTE WI-FI CONFIGURATION
// ============================================================

void acknowledgeConfiguration(int version) {
    if (WiFi.status() != WL_CONNECTED) {
        return;
    }

    WiFiClientSecure client;
    HTTPClient http;

    String url =
        String(API_BASE) +
        "/iot/device-config/" +
        CONTROLLER_CODE +
        "/ack";

    if (!beginSecureRequest(client, http, url)) {
        return;
    }

    http.addHeader(
        "Content-Type",
        "application/json"
    );

    http.addHeader(
        "Accept",
        "application/json"
    );

    http.addHeader(
        "X-Device-Key",
        DEVICE_KEY
    );

    JsonDocument doc;
    doc["config_version"] = version;

    String body;
    serializeJson(doc, body);

    int code = http.POST(body);

    Serial.printf(
        "Wi-Fi config ACK: HTTP %d\n",
        code
    );

    if (!isHttpSuccess(code)) {
        Serial.println(http.getString());
    }

    http.end();
}

void checkRemoteConfiguration() {
    if (WiFi.status() != WL_CONNECTED) {
        return;
    }

    WiFiClientSecure client;
    HTTPClient http;

    String url =
        String(API_BASE) +
        "/iot/device-config/" +
        CONTROLLER_CODE;

    if (!beginSecureRequest(client, http, url)) {
        Serial.println(
            "Failed to initialize remote-config request."
        );
        return;
    }

    http.addHeader(
        "Accept",
        "application/json"
    );

    http.addHeader(
        "X-Device-Key",
        DEVICE_KEY
    );

    int code = http.GET();

    if (!isHttpSuccess(code)) {
        Serial.printf(
            "Remote config HTTP %d\n",
            code
        );

        Serial.println(http.getString());
        http.end();
        return;
    }

    String response = http.getString();
    http.end();

    JsonDocument doc;

    DeserializationError jsonError =
        deserializeJson(doc, response);

    if (jsonError) {
        Serial.print(
            "Remote config JSON error: "
        );
        Serial.println(
            jsonError.c_str()
        );
        return;
    }

    int configVersion =
        doc["config_version"] | 0;

    int appliedVersion =
        doc["applied_version"] | 0;

    bool restartRequired =
        doc["restart_required"] | false;

    if (
        !restartRequired ||
        configVersion <= appliedVersion
    ) {
        return;
    }

    String newSSID =
        doc["wifi_ssid"].as<String>();

    String newPassword =
        doc["wifi_password"].as<String>();

    if (newSSID.length() == 0) {
        Serial.println(
            "Remote Wi-Fi config had an empty SSID."
        );
        return;
    }

    Serial.println(
        "New Wi-Fi configuration received."
    );

    // Acknowledge while the OLD Wi-Fi connection still works.
    // The credentials are stored before the restart.
    saveWiFiCredentials(
        newSSID,
        newPassword
    );

    acknowledgeConfiguration(
        configVersion
    );

    Serial.println(
        "Restarting to apply new Wi-Fi..."
    );

    delay(1000);
    ESP.restart();
}


// ============================================================
// RFID RC522
// ============================================================

String readRfidUid() {
    if (!rfid.PICC_IsNewCardPresent()) {
        return "";
    }

    if (!rfid.PICC_ReadCardSerial()) {
        return "";
    }

    String uid = "";

    for (byte i = 0; i < rfid.uid.size; i++) {
        if (rfid.uid.uidByte[i] < 0x10) {
            uid += "0";
        }

        uid += String(rfid.uid.uidByte[i], HEX);
    }

    uid.toUpperCase();

    rfid.PICC_HaltA();
    rfid.PCD_StopCrypto1();

    return uid;
}

bool sendRfidToLaravel(const String& cardUid) {
    if (WiFi.status() != WL_CONNECTED) {
        Serial.println("Cannot identify RFID: Wi-Fi disconnected.");
        return false;
    }

    WiFiClientSecure client;
    HTTPClient http;

    if (!beginSecureRequest(client, http, IDENTIFY_CARD_URL)) {
        Serial.println("Could not initialize RFID HTTPS request.");
        return false;
    }

    http.addHeader("Content-Type", "application/json");
    http.addHeader("Accept", "application/json");
    http.addHeader("X-Device-Key", DEVICE_KEY);
    http.addHeader("X-Controller-Code", CONTROLLER_CODE);

    JsonDocument requestDoc;
    requestDoc["card_uid"] = cardUid;

    String requestBody;
    serializeJson(requestDoc, requestBody);

    int responseCode = http.POST(requestBody);
    String responseBody = responseCode > 0 ? http.getString() : "";

    Serial.printf("RFID HTTP %d\n", responseCode);

    if (!isHttpSuccess(responseCode)) {
        Serial.println("RFID backend response:");
        Serial.println(responseBody);
        http.end();
        return false;
    }

    JsonDocument responseDoc;
    DeserializationError error = deserializeJson(responseDoc, responseBody);

    if (error) {
        Serial.print("RFID response JSON error: ");
        Serial.println(error.c_str());
        http.end();
        return false;
    }

    String firstName = responseDoc["student"]["first_name"] | "";
    String lastName = responseDoc["student"]["last_name"] | "";
    String studentName = firstName + " " + lastName;
    studentName.trim();

    int pointsBalance = responseDoc["points_balance"] | 0;

    Serial.println();
    Serial.println("============= RFID =============");
    Serial.print("Card UID: ");
    Serial.println(cardUid);

    if (studentName.length() > 0) {
        Serial.print("Student: ");
        Serial.println(studentName);
    }

    Serial.print("Current points: ");
    Serial.println(pointsBalance);

    if (!responseDoc["recycling_claim"].isNull() &&
        (bool)(responseDoc["recycling_claim"]["claimed"] | false)) {

        int items = responseDoc["recycling_claim"]["items"] | 0;
        int pointsAwarded = responseDoc["recycling_claim"]["points_awarded"] | 0;
        int newBalance =
            responseDoc["recycling_claim"]["new_points_balance"] | pointsBalance;

        Serial.println("Recycling session claimed.");
        Serial.print("Items claimed: ");
        Serial.println(items);
        Serial.print("Points awarded: ");
        Serial.println(pointsAwarded);
        Serial.print("New balance: ");
        Serial.println(newBalance);

        pendingSessionItems = 0;
        pendingSessionPoints = 0;
    } else {
        Serial.println(
            "Card identified. No pending recycling session was claimed."
        );
    }

    Serial.println("================================");

    http.end();
    return true;
}

void handleRfid() {
    if (classificationInProgress) {
        return;
    }

    String uid = readRfidUid();

    if (uid.length() == 0) {
        return;
    }

    unsigned long nowMs = millis();

    if (
        uid == lastRfidUid &&
        nowMs - lastRfidScanTime < RFID_SCAN_COOLDOWN_MS
    ) {
        return;
    }

    lastRfidUid = uid;
    lastRfidScanTime = nowMs;

    Serial.println();
    Serial.println("RFID card detected.");

    sendRfidToLaravel(uid);
}

// ============================================================
// CLASSIFICATION RESULT
// ============================================================

void handleClassificationResponse(
    const String& responseBody
) {
    JsonDocument doc;

    DeserializationError error =
        deserializeJson(
            doc,
            responseBody
        );

    if (error) {
        Serial.print(
            "Classification JSON error: "
        );

        Serial.println(
            error.c_str()
        );

        return;
    }

    bool success =
        doc["success"] | false;

    if (!success) {
        Serial.println(
            "Laravel reported classification failure."
        );

        return;
    }

    String rawLabel =
        doc["classification"]["label"] |
        "";

    String mappedType =
        doc["classification"]["mapped_type_name"] |
        "";

    float confidence =
        doc["classification"]["confidence"] |
        0.0f;

    int itemPoints =
        doc["classification"]["points"] |
        0;

    bool accepted =
        doc["classification"]["accepted"] |
        false;

    String compartment =
        doc["classification"]["compartment"] |
        "reject";

    int sessionItems =
        doc["session"]["items"] |
        0;

    int sessionPoints =
        doc["session"]["pending_points"] |
        0;

    lastClassification = mappedType.length()
        ? mappedType
        : rawLabel;

    lastCompartment = compartment;
    pendingSessionItems = sessionItems;
    pendingSessionPoints = sessionPoints;

    Serial.println();
    Serial.println(
        "========== CLASSIFICATION =========="
    );

    Serial.print("CNN label: ");
    Serial.println(rawLabel);

    Serial.print("Mapped material: ");
    Serial.println(mappedType);

    Serial.print("Confidence: ");
    Serial.print(confidence * 100.0f, 2);
    Serial.println("%");

    Serial.print("Accepted: ");
    Serial.println(
        accepted ? "YES" : "NO"
    );

    Serial.print("Item points: ");
    Serial.println(itemPoints);

    Serial.print("Route: ");
    Serial.println(compartment);

    Serial.print("Session items: ");
    Serial.println(sessionItems);

    Serial.print("Pending points: ");
    Serial.println(sessionPoints);

    Serial.println(
        "Tap RFID on Controller 2 when finished."
    );

    Serial.println(
        "===================================="
    );

    // Hook your segregation hardware here:
    //
    // if (compartment == "plastic") {
    //     moveServoToPlastic();
    // } else if (compartment == "paper") {
    //     moveServoToPaper();
    // } else {
    //     rejectItem();
    // }
}

// ============================================================
// CAPTURE + UPLOAD
// ============================================================

bool captureAndSendImage() {
    if (classificationInProgress) {
        return false;
    }

    if (WiFi.status() != WL_CONNECTED) {
        Serial.println(
            "Cannot classify: Wi-Fi disconnected."
        );
        return false;
    }

    classificationInProgress = true;

    Serial.println("Capturing image...");

    camera_fb_t* frameBuffer =
        esp_camera_fb_get();

    if (!frameBuffer) {
        Serial.println(
            "Camera capture failed."
        );

        classificationInProgress = false;
        return false;
    }

    Serial.printf(
        "JPEG captured: %u bytes\n",
        frameBuffer->len
    );

    WiFiClientSecure client;
    HTTPClient http;

    if (
        !beginSecureRequest(
            client,
            http,
            CLASSIFICATION_URL
        )
    ) {
        Serial.println(
            "Could not initialize HTTPS request."
        );

        esp_camera_fb_return(
            frameBuffer
        );

        classificationInProgress = false;
        return false;
    }

    http.addHeader(
        "Content-Type",
        "image/jpeg"
    );

    http.addHeader(
        "Accept",
        "application/json"
    );

    http.addHeader(
        "X-Device-Key",
        DEVICE_KEY
    );

    http.addHeader(
        "X-Controller-Code",
        CONTROLLER_CODE
    );

    int responseCode =
        http.POST(
            frameBuffer->buf,
            frameBuffer->len
        );

    // Buffer is no longer needed after POST returns.
    esp_camera_fb_return(
        frameBuffer
    );

    bool requestSuccessful = false;

    if (responseCode > 0) {
        String responseBody =
            http.getString();

        Serial.printf(
            "Classification HTTP %d\n",
            responseCode
        );

        if (
            isHttpSuccess(
                responseCode
            )
        ) {
            handleClassificationResponse(
                responseBody
            );

            requestSuccessful = true;
        } else {
            Serial.println(
                "Laravel error:"
            );

            Serial.println(
                responseBody
            );
        }
    } else {
        Serial.print(
            "HTTPS transport error: "
        );

        Serial.println(
            http.errorToString(
                responseCode
            )
        );
    }

    http.end();

    classificationInProgress = false;

    return requestSuccessful;
}

// ============================================================
// CAMERA INITIALIZATION
// ============================================================

bool initializeCamera() {
    camera_config_t config = {};

    config.ledc_channel = LEDC_CHANNEL_0;
    config.ledc_timer = LEDC_TIMER_0;

    config.pin_d0 = Y2_GPIO_NUM;
    config.pin_d1 = Y3_GPIO_NUM;
    config.pin_d2 = Y4_GPIO_NUM;
    config.pin_d3 = Y5_GPIO_NUM;
    config.pin_d4 = Y6_GPIO_NUM;
    config.pin_d5 = Y7_GPIO_NUM;
    config.pin_d6 = Y8_GPIO_NUM;
    config.pin_d7 = Y9_GPIO_NUM;

    config.pin_xclk = XCLK_GPIO_NUM;
    config.pin_pclk = PCLK_GPIO_NUM;
    config.pin_vsync = VSYNC_GPIO_NUM;
    config.pin_href = HREF_GPIO_NUM;

    config.pin_sccb_sda = SIOD_GPIO_NUM;
    config.pin_sccb_scl = SIOC_GPIO_NUM;

    config.pin_pwdn = PWDN_GPIO_NUM;
    config.pin_reset = RESET_GPIO_NUM;

    config.xclk_freq_hz = 20000000;
    config.pixel_format = PIXFORMAT_JPEG;

    config.grab_mode = CAMERA_GRAB_WHEN_EMPTY;
    config.fb_location = CAMERA_FB_IN_PSRAM;

    config.frame_size = FRAMESIZE_QVGA;
    config.jpeg_quality = 12;
    config.fb_count = 1;

    if (psramFound()) {
        Serial.println("PSRAM found.");

        config.frame_size = FRAMESIZE_VGA;
        config.jpeg_quality = 12;
        config.fb_count = 2;
        config.grab_mode = CAMERA_GRAB_LATEST;
        config.fb_location = CAMERA_FB_IN_PSRAM;
    } else {
        Serial.println(
            "PSRAM not found. Using DRAM."
        );

        config.frame_size = FRAMESIZE_QVGA;
        config.jpeg_quality = 15;
        config.fb_count = 1;
        config.fb_location = CAMERA_FB_IN_DRAM;
    }

#if defined(CAMERA_MODEL_ESP_EYE)
    pinMode(13, INPUT_PULLUP);
    pinMode(14, INPUT_PULLUP);
#endif

    esp_err_t cameraError =
        esp_camera_init(&config);

    if (cameraError != ESP_OK) {
        Serial.printf(
            "Camera initialization failed: 0x%x\n",
            cameraError
        );
        return false;
    }

    sensor_t* sensor =
        esp_camera_sensor_get();

    if (!sensor) {
        Serial.println(
            "Could not access camera sensor."
        );
        return false;
    }

    if (sensor->id.PID == OV3660_PID) {
        sensor->set_vflip(sensor, 1);
        sensor->set_brightness(sensor, 1);
        sensor->set_saturation(sensor, -2);
    }

#if defined(CAMERA_MODEL_ESP32S3_EYE)
    sensor->set_vflip(sensor, 1);
#endif

    // CNN API resizes to 255 x 255.
    sensor->set_framesize(
        sensor,
        FRAMESIZE_VGA
    );

    Serial.println(
        "Camera initialized successfully."
    );

    return true;
}

// ============================================================
// RFID INITIALIZATION
// ============================================================

void initializeRfid() {
    SPI.begin(
        RFID_SCK_PIN,
        RFID_MISO_PIN,
        RFID_MOSI_PIN,
        RFID_SS_PIN
    );

    rfid.PCD_Init();
    delay(50);

    byte version =
        rfid.PCD_ReadRegister(MFRC522::VersionReg);

    Serial.print("RC522 firmware version: 0x");
    Serial.println(version, HEX);

    if (version == 0x00 || version == 0xFF) {
        Serial.println(
            "WARNING: RC522 not detected. Check 3.3V, GND, and SPI wiring."
        );
    } else {
        Serial.println("RC522 initialized successfully.");
    }
}

// ============================================================
// SETUP
// ============================================================

void setup() {
    Serial.begin(115200);
    Serial.setDebugOutput(true);

    delay(1000);

    Serial.println();
    Serial.println(
        "Starting PLink Controller 1..."
    );

    pinMode(
        IR_SENSOR_PIN,
        INPUT_PULLUP
    );

    if (!initializeCamera()) {
        Serial.println(
            "Stopping: camera initialization failed."
        );
        return;
    }

#if defined(LED_GPIO_NUM)
    setupLedFlash();
#endif

    initializeRfid();

    connectToWiFi();

    if (WiFi.status() == WL_CONNECTED) {
        // Optional diagnostic stream; classification itself sends
        // only one JPEG per IR trigger.
        startCameraServer();

        Serial.print(
            "Camera server: http://"
        );

        Serial.println(
            WiFi.localIP()
        );

        // Check for a queued Wi-Fi configuration once at boot.
        checkRemoteConfiguration();
        lastConfigCheck = millis();
    }

    Serial.println();
    Serial.println(
        "Controller 1 ready."
    );

    Serial.println(
        "Waiting for recyclable..."
    );
}

// ============================================================
// LOOP
// ============================================================

void loop() {
    maintainWiFi();

    // Tap RFID after depositing. The backend identify-card endpoint
    // claims the current pending recycling session and awards points.
    handleRfid();

    unsigned long nowMs =
        millis();

    if (
        WiFi.status() == WL_CONNECTED &&
        nowMs - lastConfigCheck >=
            CONFIG_CHECK_INTERVAL_MS
    ) {
        lastConfigCheck = nowMs;
        checkRemoteConfiguration();
    }

    bool objectDetected =
        digitalRead(IR_SENSOR_PIN) ==
        IR_DETECTED_STATE;

    bool cooldownFinished =
        nowMs - lastCaptureTime >=
        CAPTURE_COOLDOWN_MS;

    // One image per object-presence transition.
    if (
        objectDetected &&
        !objectPreviouslyDetected &&
        cooldownFinished &&
        !classificationInProgress
    ) {
        Serial.println();
        Serial.println(
            "IR sensor detected an object."
        );

        delay(
            CAPTURE_DELAY_MS
        );

        bool stillPresent =
            digitalRead(IR_SENSOR_PIN) ==
            IR_DETECTED_STATE;

        if (stillPresent) {
            bool success =
                captureAndSendImage();

            Serial.println(
                success
                    ? "Classification completed."
                    : "Classification failed."
            );

            lastCaptureTime =
                millis();
        } else {
            Serial.println(
                "Object disappeared before capture."
            );
        }
    }

    // Sensor must clear before another item can trigger.
    objectPreviouslyDetected =
        objectDetected;

    delay(50);
}
