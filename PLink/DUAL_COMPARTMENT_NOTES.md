# Dual-Compartment Smart Bin Revision

Smart Recycling Bin 1 now has two independent physical compartments:

- `plastic` — Plastic Compartment
- `paper` — Paper Compartment

Each compartment has its own HC-SR04 state in `smart_bin_compartments`:

- `current_distance_cm`
- `current_fill_percentage`
- `full_threshold_cm`
- `empty_threshold_cm`
- `status`
- `last_active_at`

Historical readings are stored in `smart_bin_compartment_logs`.

## Existing database

Run:

```bash
php artisan migrate
php artisan optimize:clear
```

The migration automatically creates Plastic and Paper compartments for existing Smart Bins. The existing Smart Bin reading is assigned to the Plastic Compartment; the Paper Compartment starts empty until its HC-SR04 sensor sends a reading.

## Fresh development database

```bash
php artisan migrate:fresh --seed
```

The seeder creates both compartments and sample sensor histories.

## ESP32 sensor requests

Each HC-SR04 should send its distance separately.

Plastic example:

```http
PATCH /api/machines/1/compartments/1/sensor
Content-Type: application/json

{
  "distance_cm": 42
}
```

Paper example:

```http
PATCH /api/machines/1/compartments/2/sensor
Content-Type: application/json

{
  "distance_cm": 58
}
```

Laravel calculates the fill percentage from the compartment's thresholds, logs the reading, and synchronizes the legacy overall Smart Bin fullness with the fullest compartment so the existing dashboard remains compatible.

## Recyclable material categories

`recyclable_types` now has `material_category` (`plastic`, `paper`, or `other`). Existing paper and invalid rows are corrected by the migration.
