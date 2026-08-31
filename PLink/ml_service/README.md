# PLink Prophet service

The Prophet service runs privately on the same EC2 instance as Laravel at `127.0.0.1:5001`.

Endpoints:

- `GET /health` - service and saved-model status
- `GET /models` - trained-model metadata
- `POST /train-all` - receives historical `ds`/`y` datasets from Laravel, fits models, and saves them
- `POST /forecast-all` - loads saved models and generates forecasts without retraining
- `POST /forecast` - optional single-model forecast

Laravel should use `PROPHET_API_URL=http://127.0.0.1:5001` in production.

First EC2 setup (once):

```bash
sudo apt update
sudo apt install -y python3 python3-venv python3-pip
cd /var/www/plink-backend/PLink/ml_service
python3 -m venv venv
./venv/bin/pip install -r requirements.txt
sudo cp ../deployment/systemd/plink-prophet.service /etc/systemd/system/plink-prophet.service
sudo systemctl daemon-reload
sudo systemctl enable --now plink-prophet
curl http://127.0.0.1:5001/health
```

After the service is running, perform the first model training from the Laravel project:

```bash
php artisan prophet:train
```
