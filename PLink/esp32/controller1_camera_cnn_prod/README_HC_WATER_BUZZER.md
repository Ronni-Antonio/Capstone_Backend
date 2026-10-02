# PLink Controller 1 — HC-SR04 #3 + Water Sensor + Active Buzzer

This revision keeps the existing camera, RC522, two bin-fullness HC-SR04 sensors, Wi-Fi/API flow, and MG995 sorting logic.

## New / revised wiring

### HC-SR04 #3 — intake/object detection (replaces old IR sensor)
- TRIG -> GPIO42
- ECHO -> GPIO21 **through a 5V-to-3.3V voltage divider**
- VCC -> 5V
- GND -> GND

Suggested ECHO divider:
- HC-SR04 ECHO -> 1k resistor -> junction -> GPIO21
- junction -> 2k resistor -> GND

Use the same type of divider on the other HC-SR04 ECHO pins as well.

### Flat water/rain sensor module
- DO -> GPIO14
- VCC -> 3.3V
- GND -> GND
- AO is not used in this revision.

The code assumes `LOW = wet` (`WATER_WET_STATE LOW`). If the module behaves opposite during testing, change it to `HIGH`.

### Active buzzer module
- I/O -> GPIO48
- VCC -> 3.3V **if the module is rated for 3.3–5V**
- GND -> GND

The code assumes active-HIGH. For a low-level-trigger buzzer module, change:

```cpp
#define BUZZER_ACTIVE_STATE LOW
```

### MG995 servo (unchanged)
- Signal -> GPIO47
- Red -> external regulated 5–6V servo supply
- Brown/Black -> external servo-supply GND
- ESP32 GND -> same external servo-supply GND (common ground)

Do not power the MG995 from the ESP32 3.3V/5V pin.

## Existing hardware pins retained

### RC522
- SS/SDA -> GPIO41
- RST -> GPIO2
- SCK -> GPIO40
- MOSI -> GPIO38
- MISO -> GPIO39
- VCC -> 3.3V
- GND -> GND

### HC-SR04 #1 — plastic compartment fullness
- TRIG -> GPIO1
- ECHO -> GPIO3 through voltage divider

### HC-SR04 #2 — paper compartment fullness
- TRIG -> GPIO45
- ECHO -> GPIO46 through voltage divider

## Processing flow

1. HC-SR04 #3 detects an object within the configured intake distance.
2. The object is given a short settling delay.
3. The water sensor is sampled several times.
4. If wet, the item is rejected locally: no image upload, no buzzer, no servo routing, and no new pending-points classification is created.
5. If dry, the ESP32-S3-CAM captures a JPEG and POSTs it to the existing `/api/iot/classify` CNN endpoint.
6. If Laravel says the classification is accepted and maps it to `plastic` or `paper`, the active buzzer beeps once.
7. The MG995 then routes the item to the mapped compartment and returns to center.
8. The current backend's classification endpoint returns the updated pending session items/points. Those points remain pending until the student finishes depositing and taps RFID on Controller 2 to claim them.
9. HC-SR04 #3 must clear before the next recyclable can trigger another cycle.

## Tuning values

In the sketch:

```cpp
const float OBJECT_DETECT_DISTANCE_CM = 12.0f;
const float OBJECT_CLEAR_DISTANCE_CM = 18.0f;
```

Adjust these after mounting HC-SR04 #3 in the actual chute.

The water sensor's blue potentiometer should also be calibrated using your actual dry/wet paper and bottle samples.

## Important backend behavior

The existing `/api/iot/classify` call already returns and updates `session.pending_points`. For that reason this revision checks wet/dry **before** uploading the image. This prevents a wet recyclable from being submitted to an endpoint that may create pending session points.

Actual student balance credit still occurs later through your existing RFID claim flow on Controller 2.

If you want Laravel to explicitly store wet/rejected attempts as database records, that requires a separate backend endpoint or a `wet` field in the classification API; this sketch does not add that backend behavior.
