# ESP32 Remote Wi-Fi Configuration

The website cannot directly open a connection to an ESP32 that is behind a router/NAT. The implemented design uses a **desired-configuration polling model**:

1. Admin opens **System Settings → ESP32 Controller Wi-Fi**.
2. React sends the desired SSID/password to Laravel.
3. Laravel stores the password encrypted in `iot_device_configs` and increments `config_version`.
4. Each ESP32 periodically polls:
   - `GET /api/iot/device-config/controller-1`, or
   - `GET /api/iot/device-config/controller-2`
5. The ESP32 includes its own `X-Device-Key` header.
6. If `config_version` is newer than the version stored in ESP32 Preferences/NVS, the ESP32 stores the new credentials and reconnects/restarts.
7. After it successfully reconnects, it acknowledges the applied version using:
   - `POST /api/iot/device-config/{controllerCode}/ack`

## Production environment variables

Add long random values to the EC2 `.env`:

```env
IOT_CONTROLLER_1_KEY=replace-with-a-long-random-secret
IOT_CONTROLLER_2_KEY=replace-with-another-long-random-secret
```

Then run:

```bash
php artisan optimize:clear
php artisan config:cache
```

The corresponding key is compiled/stored in each controller's firmware. Do not put these keys in the React frontend.

## ESP32 request example

```cpp
#include <WiFi.h>
#include <WiFiClientSecure.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <Preferences.h>

Preferences prefs;

const char* API_BASE = "https://plink-api.barabesta.is";
const char* CONTROLLER_CODE = "controller-1"; // change to controller-2 on the second ESP32
const char* DEVICE_KEY = "replace-with-controller-1-key";

bool fetchDesiredWifi() {
  if (WiFi.status() != WL_CONNECTED) return false;

  WiFiClientSecure client;
  client.setInsecure(); // Prototype only. Use the proper CA certificate in production.

  HTTPClient http;
  String url = String(API_BASE) + "/api/iot/device-config/" + CONTROLLER_CODE;

  if (!http.begin(client, url)) return false;
  http.addHeader("Accept", "application/json");
  http.addHeader("X-Device-Key", DEVICE_KEY);

  int status = http.GET();
  if (status != HTTP_CODE_OK) {
    http.end();
    return false;
  }

  JsonDocument doc;
  DeserializationError error = deserializeJson(doc, http.getString());
  http.end();
  if (error) return false;

  unsigned long remoteVersion = doc["config_version"] | 0;
  unsigned long localVersion = prefs.getULong("wifi_ver", 0);
  if (remoteVersion <= localVersion) return true;

  String newSsid = doc["wifi_ssid"] | "";
  String newPassword = doc["wifi_password"] | "";
  if (newSsid.isEmpty()) return false;

  prefs.putString("ssid", newSsid);
  prefs.putString("wifi_pass", newPassword);
  prefs.putULong("pending_ver", remoteVersion);

  ESP.restart();
  return true;
}
```

On boot, connect using the stored `ssid` / `wifi_pass`. Once the new connection succeeds, POST the `pending_ver` to the ACK endpoint and then copy it to `wifi_ver`.

## Recovery requirement

A remotely supplied password can be wrong. If that happens, the controller cannot reach the backend to fix itself. Keep a recovery method such as:

- a temporary ESP32 setup access point,
- a physical reset/provision button, or
- USB/serial provisioning.

Remote Wi-Fi changes should therefore be treated as **queued desired configuration**, not a guaranteed push operation.
