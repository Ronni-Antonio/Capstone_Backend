# PLink Controller 2 command queue

The old firmware depended on Laravel calling a local ESP32 endpoint such as `/prepare-activation` or `/prepare-redemption`. That is not suitable after Laravel is deployed on EC2 because EC2 cannot normally initiate a connection to an ESP32 behind a home/school NAT router.

This revision changes Controller 2 to an outbound-only HTTPS client.

## RFID assignment

1. React calls `POST /api/students/{id}/activate`.
2. Laravel queues `assign_card` for `controller-2` in `iot_controller_commands`.
3. ESP32 polls `GET /api/iot/controller-commands/controller-2/next`.
4. ESP32 enters assignment mode and waits for a physical tap.
5. ESP32 posts the tapped UID to `POST /api/students/assign-card`.
6. Laravel assigns the card and activates the student.
7. ESP32 ACKs the queued command as completed.
8. Existing React activation-status polling sees success.

## Standard identification / reward selection

1. Rewards UI clears the old scan session and polls `GET /api/students/active-scan-session`.
2. Student taps RFID while Controller 2 is idle.
3. ESP32 calls `POST /api/students/identify-card`.
4. Laravel caches the successful identification for two minutes.
5. The browser sees the cached scan and displays the student/reward choices.

## Redemption confirmation

1. React calls `POST /api/redemptions/initiate/{student_id}/{reward_id}`.
2. Laravel validates reward active status, stock, and student points, then queues `confirm_redemption`.
3. ESP32 polls it and waits for the student's second tap.
4. ESP32 calls `POST /api/redemptions` with `student_id`, `reward_id`, and `card_uid`.
5. Laravel verifies that the UID belongs to the selected student and re-checks points, stock, and reward active status inside the DB transaction.
6. Laravel creates redemption and point transaction rows.
7. ESP32 ACKs the command as completed.
8. Existing frontend redemption polling sees the completed redemption.

## New endpoints

```text
GET  /api/iot/controller-commands/{controllerCode}/next
POST /api/iot/controller-commands/{controllerCode}/{commandId}/ack
```

Both require:

```text
X-Device-Key: <matching controller secret>
```

## New migration

```text
2026_09_08_000001_create_iot_controller_commands_table.php
```

Command states are `pending`, `claimed`, `completed`, `failed`, `cancelled`, and `expired`.

## Production deployment

Set in EC2 `.env`:

```env
IOT_CONTROLLER_2_KEY=<random secret>
```

Generate one with:

```bash
openssl rand -hex 32
```

Then deploy and run:

```bash
cd /var/www/plink-backend/PLink
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:list --path=iot/controller-commands
```

Flash `esp32/controller2_rfid_production.ino` after replacing:

```cpp
REPLACE_WITH_CONTROLLER_2_DEVICE_KEY
```

with the same secret.

`WiFiClientSecure::setInsecure()` is used for prototype convenience. Replace it with CA validation for final production.
