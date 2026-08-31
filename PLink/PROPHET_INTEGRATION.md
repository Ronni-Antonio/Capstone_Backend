# PLink Prophet integration

## Runtime architecture

- React calls Laravel only.
- Laravel runs on EC2 behind Nginx/PHP-FPM.
- Python Prophet runs on the same EC2 instance through Gunicorn at `127.0.0.1:5001`.
- Laravel sends historical data to Python only during training.
- Forecast requests load saved Prophet models and do not call `fit()` again.
- Forecast results are written to the existing `predictions` table and are then read by `ReportsAnalyticsController` for the frontend.

## Laravel API

- `GET /api/prophet/status`
- `GET /api/prophet/models`
- `POST /api/prophet/train`
- `POST /api/prophet/run-forecast` with `{ "periods": 7 }`

## Weekly retraining

`routes/console.php` schedules `php artisan prophet:train` every Sunday at 02:00 Asia/Manila time. The deployment contains `/deployment/cron/plink-scheduler`, which invokes Laravel's scheduler every minute on EC2.

The first model training should be run manually after the first successful deployment:

```bash
cd /var/www/plink-backend/PLink
php artisan prophet:train
```

## Production environment

Keep this in the EC2 Laravel `.env`:

```env
PROPHET_API_URL=http://127.0.0.1:5001
```

Then refresh config:

```bash
php artisan config:clear
php artisan config:cache
```

## One-time EC2 prerequisites

The CD workflow manages the Python venv and systemd service, but Python/venv support must exist on the instance first:

```bash
sudo apt update
sudo apt install -y python3 python3-venv python3-pip
```

The deploy workflow installs `deployment/systemd/plink-prophet.service`, updates `ml_service/requirements.txt`, restarts the Prophet service, installs the Laravel scheduler cron entry, and verifies `/health`.

`ml_service/venv/` and `ml_service/saved_models/` are excluded from deployment deletion so trained models survive normal code deployments.
