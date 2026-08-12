# Performance Optimization Notes

## Main changes
- Added `GET /api/dashboard` as a lightweight initial-load endpoint.
- Dashboard aggregates are cached for 15 seconds.
- Student list now selects only list fields, loads only active RFID cards, and includes `total_items_recycled` using SQL aggregation.
- Transaction list now returns a lightweight summary by default. Full nested transaction data is still available through `GET /api/transactions/{id}`; use `?summary=0` for paginated detailed lists.
- Rewrote `/api/sections/ranking` to use aggregate SQL instead of N+1 queries and fixed the previous `bottles_rank` error.
- Added indexes for forecasting/dashboard date filters.
- Removed stale transaction updates to the old `total_weight_kg` column in the revised no-weight transaction model.

## After replacing the backend
Run:

```bash
php artisan migrate
php artisan optimize:clear
```

For a production deployment, also run:

```bash
php artisan optimize
```

## Frontend behavior expected
The matching optimized frontend now requests only `/api/dashboard` immediately after login. Other datasets are loaded when their page is opened.
