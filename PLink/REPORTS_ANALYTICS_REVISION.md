# Reports & Analytics Revision

## New API endpoints

- `GET /api/reports-analytics?days=30`
- `GET /api/reports-analytics/pdf?days=30`
- `GET /api/prophet/forecast-data`
- `POST /api/prophet/run-forecast` with `{ "periods": 7 }`

## Reports & Analytics data

The reports page now uses server-side aggregates instead of downloading all students and transactions into React. It includes:

- total recyclable items
- participating students
- points awarded
- reward redemptions
- recycling trend
- recyclable type distribution (PET, HDPE, PP, Paper, etc.)
- section performance
- top 5 recyclers by points earned
- reward redemption breakdown
- plastic/paper compartment fullness
- Prophet forecasts for recycling volume, student participation, reward redemptions, plastic fullness, and paper fullness

## Prophet service

See `ml_service/README.md`.

Add to Laravel `.env` if necessary:

```env
PROPHET_API_URL=http://127.0.0.1:5001
```

The Python service runs separately on port 5001. Laravel sends prepared historical `ds/y` series to it and stores the returned forecast points in the `predictions` table.

## PDF report

`GET /api/reports-analytics/pdf` generates a downloadable PDF without an additional Composer dependency. A report snapshot is also recorded in `analytics_reports`.

## Database change

Run:

```bash
php artisan migrate
php artisan optimize:clear
```

The new migration removes the obsolete `analytics_reports.total_weight_kg` column, since the revised Smart Bin is distance/compartment based.
