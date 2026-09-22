#include <Arduino.h>
#include "esp_camera.h"

#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <Preferences.h>
#include <ESP32Servo.h>
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

// ============================================================
// FALLBACK WI-FI
// ============================================================

static const char* FALLBACK_SSID =
    "Roni :3";

static const char* FALLBACK_PASSWORD =
    "p00pyp4nt5";

// ============================================================
// CONTROLLER 1 HARDWARE
// ============================================================


#define IR_SENSOR_PIN 42
#define IR_DETECTED_STATE LOW

// Existing RC522 wiring from the current hardware diagram.
#define RFID_SS_PIN   41
#define RFID_RST_PIN  2
#define RFID_SCK_PIN  40
#define RFID_MOSI_PIN 38
#define RFID_MISO_PIN 39

// HC-SR04 #1: PLASTIC compartment
#define HC_PLASTIC_TRIG_PIN 1
#define HC_PLASTIC_ECHO_PIN 3

// HC-SR04 #2: PAPER compartment
#define HC_PAPER_TRIG_PIN 45
#define HC_PAPER_ECHO_PIN 46

// Servo signal
#define SERVO_PIN 47

#define SMART_BIN_ID 1

int plasticCompartmentId = -1;
int paperCompartmentId = -1;

// Servo positions. Adjust these mechanically after testing.
const int SERVO_CENTER_ANGLE = 90;
const int SERVO_PLASTIC_ANGLE = 35;   // LEFT
const int SERVO_PAPER_ANGLE = 145;    // RIGHT

const unsigned long SERVO_HOLD_MS = 900;

// HC-SR04 settings
const unsigned long ULTRASONIC_TIMEOUT_US = 30000;
const unsigned long BIN_SENSOR_INTERVAL_MS = 5000;
const float MAX_VALID_DISTANCE_CM = 400.0f;
const int DISTANCE_SAMPLES = 5;

// Fill readings are sent as RAW DISTANCE values.
// Laravel remains responsible for converting distance -> percentage.
unsigned long lastBinSensorUpload = 0;

// Hardware objects
MFRC522 rfid(RFID_SS_PIN, RFID_RST_PIN);
Servo sorterServo;

const unsigned long CAPTURE_DELAY_MS = 500;
const unsigned long CAPTURE_COOLDOWN_MS = 3000;

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

// Existing camera-server helpers from the ESP32 camera example.
void startCameraServer();
void setupLedFlash();

// Hardware helpers
float readUltrasonicDistanceCM(
    int trigPin,
    int echoPin
);

float readStableDistanceCM(
    int trigPin,
    int echoPin
);

void updateBinSensors();
bool discoverCompartmentIds();
bool uploadCompartmentDistance(
    const char* compartmentCode,
    float distanceCm
);

void routeClassifiedItem(
    const String& compartment
);

void moveSorterServo(
    int angle
);

void printRFIDDiagnostic();

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

    // Physical segregation is controlled from the Laravel mapping.
    // The CNN only classifies; Laravel decides the mapped compartment.
    routeClassifiedItem(compartment);
}

// ============================================================
// SERVO SORTING
// ============================================================

void moveSorterServo(int angle) {
    angle = constrain(angle, 0, 180);

    sorterServo.write(angle);

    Serial.print("Servo angle: ");
    Serial.println(angle);
}

void routeClassifiedItem(const String& compartment) {
    if (compartment == "plastic") {
        Serial.println("Routing item: PLASTIC -> LEFT");

        moveSorterServo(SERVO_PLASTIC_ANGLE);
        delay(SERVO_HOLD_MS);

        moveSorterServo(SERVO_CENTER_ANGLE);
        Serial.println("Sorter returned to CENTER.");
        return;
    }

    if (compartment == "paper") {
        Serial.println("Routing item: WHITE PAPER -> RIGHT");

        moveSorterServo(SERVO_PAPER_ANGLE);
        delay(SERVO_HOLD_MS);

        moveSorterServo(SERVO_CENTER_ANGLE);
        Serial.println("Sorter returned to CENTER.");
        return;
    }

    Serial.println(
        "Routing item: REJECT / UNKNOWN -> CENTER"
    );

    moveSorterServo(SERVO_CENTER_ANGLE);
}

// ============================================================
// HC-SR04 DISTANCE
// ============================================================

float readUltrasonicDistanceCM(
    int trigPin,
    int echoPin
) {
    digitalWrite(trigPin, LOW);
    delayMicroseconds(3);

    digitalWrite(trigPin, HIGH);
    delayMicroseconds(10);
    digitalWrite(trigPin, LOW);

    unsigned long duration =
        pulseIn(
            echoPin,
            HIGH,
            ULTRASONIC_TIMEOUT_US
        );

    if (duration == 0) {
        return -1.0f;
    }

    float distanceCm =
        (duration * 0.0343f) / 2.0f;

    if (
        distanceCm <= 0.0f ||
        distanceCm > MAX_VALID_DISTANCE_CM
    ) {
        return -1.0f;
    }

    return distanceCm;
}

float readStableDistanceCM(
    int trigPin,
    int echoPin
) {
    float total = 0.0f;
    int validSamples = 0;

    for (int i = 0; i < DISTANCE_SAMPLES; i++) {
        float distance =
            readUltrasonicDistanceCM(
                trigPin,
                echoPin
            );

        if (distance > 0.0f) {
            total += distance;
            validSamples++;
        }

        // Small separation between samples reduces echo interference.
        delay(60);
    }

    if (validSamples == 0) {
        return -1.0f;
    }

    return total / validSamples;
}

// ============================================================
// COMPARTMENT ID DISCOVERY
// ============================================================

bool discoverCompartmentIds() {
    if (WiFi.status() != WL_CONNECTED) {
        return false;
    }

    WiFiClientSecure client;
    HTTPClient http;

    String url =
        String(API_BASE) +
        "/machines/" +
        String(SMART_BIN_ID);

    if (!beginSecureRequest(client, http, url)) {
        Serial.println(
            "Could not initialize smart-bin discovery request."
        );
        return false;
    }

    http.addHeader(
        "Accept",
        "application/json"
    );

    http.addHeader(
        "X-Device-Key",
        DEVICE_KEY
    );

    int responseCode = http.GET();

    if (!isHttpSuccess(responseCode)) {
        Serial.printf(
            "Smart-bin discovery HTTP %d\n",
            responseCode
        );
        Serial.println(http.getString());
        http.end();
        return false;
    }

    String responseBody = http.getString();
    http.end();

    JsonDocument doc;

    DeserializationError error =
        deserializeJson(doc, responseBody);

    if (error) {
        Serial.print(
            "Smart-bin discovery JSON error: "
        );
        Serial.println(error.c_str());
        return false;
    }

    plasticCompartmentId = -1;
    paperCompartmentId = -1;

    JsonArray compartments =
        doc["compartments"].as<JsonArray>();

    for (JsonObject compartment : compartments) {
        int id =
            compartment["compartment_id"] | -1;

        String material =
            compartment["material_category"] |
            "";

        material.toLowerCase();

        if (material == "plastic") {
            plasticCompartmentId = id;
        } else if (material == "paper") {
            paperCompartmentId = id;
        }
    }

    Serial.print("Plastic compartment ID: ");
    Serial.println(plasticCompartmentId);

    Serial.print("Paper compartment ID: ");
    Serial.println(paperCompartmentId);

    return
        plasticCompartmentId > 0 &&
        paperCompartmentId > 0;
}

// ============================================================
// BIN SENSOR API
// ============================================================

bool uploadCompartmentDistance(
    int compartmentId,
    const char* materialName,
    float distanceCm
) {
    if (WiFi.status() != WL_CONNECTED) {
        Serial.println(
            "Cannot upload bin distance: Wi-Fi disconnected."
        );
        return false;
    }

    WiFiClientSecure client;
    HTTPClient http;

    String url =
        String(API_BASE) +
        "/machines/" +
        String(SMART_BIN_ID) +
        "/compartments/" +
        String(compartmentId) +
        "/sensor";

    if (!beginSecureRequest(client, http, url)) {
        Serial.println(
            "Could not initialize bin sensor HTTPS request."
        );
        return false;
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

    http.addHeader(
        "X-Controller-Code",
        CONTROLLER_CODE
    );

    JsonDocument requestDoc;

    requestDoc["distance_cm"] = distanceCm;

    String requestBody;
    serializeJson(requestDoc, requestBody);

    int responseCode =
        http.PATCH(requestBody);

    String responseBody =
        http.getString();

    http.end();

    Serial.printf(
        "Bin sensor [%s] [ID %d]: %.2f cm -> HTTP %d\n",
        materialName,
        compartmentId,
        distanceCm,
        responseCode
    );

    if (!isHttpSuccess(responseCode)) {
        Serial.println(responseBody);
        return false;
    }

    // Print the backend-calculated fullness when available.
    JsonDocument responseDoc;

    if (
        deserializeJson(
            responseDoc,
            responseBody
        ) == DeserializationError::Ok
    ) {
        float fill =
            responseDoc["data"]["current_fill_percentage"] |
            responseDoc["current_fill_percentage"] |
            -1.0f;

        String status =
            responseDoc["data"]["status"] |
            responseDoc["status"] |
            "";

        if (fill >= 0.0f) {
            Serial.print("  Fill: ");
            Serial.print(fill, 1);
            Serial.println("%");
        }

        if (status.length()) {
            Serial.print("  Status: ");
            Serial.println(status);
        }
    }

    return true;
}

void updateBinSensors() {
    if (WiFi.status() != WL_CONNECTED) {
        return;
    }

    if (
        plasticCompartmentId <= 0 ||
        paperCompartmentId <= 0
    ) {
        if (!discoverCompartmentIds()) {
            Serial.println(
                "Cannot upload fullness: compartment IDs were not discovered."
            );
            return;
        }
    }

    Serial.println();
    Serial.println("========== BIN FULLNESS ==========");

    // Read PLASTIC first, then PAPER. The sensors are not triggered
    // simultaneously, which reduces ultrasonic cross-talk.
    float plasticDistance =
        readStableDistanceCM(
            HC_PLASTIC_TRIG_PIN,
            HC_PLASTIC_ECHO_PIN
        );

    Serial.print("Plastic distance: ");

    if (plasticDistance < 0.0f) {
        Serial.println("INVALID / NO ECHO");
    } else {
        Serial.print(plasticDistance, 2);
        Serial.println(" cm");

        uploadCompartmentDistance(
            plasticCompartmentId,
            "plastic",
            plasticDistance
        );
    }

    // Allow echoes to settle before using the second sensor.
    delay(100);

    float paperDistance =
        readStableDistanceCM(
            HC_PAPER_TRIG_PIN,
            HC_PAPER_ECHO_PIN
        );

    Serial.print("Paper distance: ");

    if (paperDistance < 0.0f) {
        Serial.println("INVALID / NO ECHO");
    } else {
        Serial.print(paperDistance, 2);
        Serial.println(" cm");

        uploadCompartmentDistance(
            paperCompartmentId,
            "paper",
            paperDistance
        );
    }

    Serial.println("==================================");
}

// ============================================================
// RFID DIAGNOSTIC
// ============================================================
// Controller 2 remains responsible for student identification and
// reward claiming. Controller 1 only initializes the RC522 and can
// report a UID for wiring diagnostics.

void printRFIDDiagnostic() {
    if (!rfid.PICC_IsNewCardPresent()) {
        return;
    }

    if (!rfid.PICC_ReadCardSerial()) {
        return;
    }

    Serial.print("Controller 1 RFID detected UID: ");

    for (byte i = 0; i < rfid.uid.size; i++) {
        if (rfid.uid.uidByte[i] < 0x10) {
            Serial.print("0");
        }

        Serial.print(
            rfid.uid.uidByte[i],
            HEX
        );

        if (i + 1 < rfid.uid.size) {
            Serial.print(":");
        }
    }

    Serial.println();

    rfid.PICC_HaltA();
    rfid.PCD_StopCrypto1();
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

    // HC-SR04 pins
    pinMode(
        HC_PLASTIC_TRIG_PIN,
        OUTPUT
    );
    digitalWrite(
        HC_PLASTIC_TRIG_PIN,
        LOW
    );

    pinMode(
        HC_PLASTIC_ECHO_PIN,
        INPUT
    );

    pinMode(
        HC_PAPER_TRIG_PIN,
        OUTPUT
    );
    digitalWrite(
        HC_PAPER_TRIG_PIN,
        LOW
    );

    pinMode(
        HC_PAPER_ECHO_PIN,
        INPUT
    );

    // Servo
    sorterServo.setPeriodHertz(50);
    sorterServo.attach(
        SERVO_PIN,
        500,
        2400
    );
    moveSorterServo(
        SERVO_CENTER_ANGLE
    );

    // RC522 diagnostic initialization.
    // This preserves the current wiring:
    // SCK=40, MISO=39, MOSI=38, SS=41, RST=2.
    SPI.begin(
        RFID_SCK_PIN,
        RFID_MISO_PIN,
        RFID_MOSI_PIN,
        RFID_SS_PIN
    );

    rfid.PCD_Init();

    Serial.println("RC522 initialized.");

    if (!initializeCamera()) {
        Serial.println(
            "Stopping: camera initialization failed."
        );
        return;
    }

#if defined(LED_GPIO_NUM)
    setupLedFlash();
#endif

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

        // Discover the actual plastic/paper compartment IDs from Laravel.
        discoverCompartmentIds();
    }

    Serial.println();
    Serial.println(
        "Controller 1 ready."
    );

    Serial.println(
        "Waiting for recyclable..."
    );

    // Take the first fullness reading after boot.
    lastBinSensorUpload =
        millis() - BIN_SENSOR_INTERVAL_MS;
}

// ============================================================
// LOOP
// ============================================================

void loop() {
    maintainWiFi();

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

    if (
        WiFi.status() == WL_CONNECTED &&
        nowMs - lastBinSensorUpload >=
            BIN_SENSOR_INTERVAL_MS &&
        !classificationInProgress
    ) {
        lastBinSensorUpload = nowMs;
        updateBinSensors();
    }

    // Diagnostic only. Controller 2 remains the production RFID
    // controller for student identification and reward claiming.
    printRFIDDiagnostic();

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
