#!/usr/bin/env bash
set -e

APP_DIR="/var/www/plink-backend/PLink"
ML_DIR="$APP_DIR/ml_service"

cd "$APP_DIR"

# ====================================================
# BASIC CHECKS
# ====================================================

test -f .env || {
  echo "ERROR: Production .env is missing."
  exit 1
}

command -v php >/dev/null || {
  echo "ERROR: PHP is not installed."
  exit 1
}

command -v composer >/dev/null || {
  echo "ERROR: Composer is not installed."
  exit 1
}

command -v python3 >/dev/null || {
  echo "ERROR: Python3 is not installed."
  exit 1
}

command -v curl >/dev/null || {
  echo "ERROR: curl is not installed."
  exit 1
}

# ====================================================
# PHP EXTENSIONS REQUIRED BY LARAVEL
# ====================================================

echo "Checking/installing required PHP extensions..."

PHP_VERSION_SHORT="$(php -r "echo PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION;")"

if ! php -m | grep -qiE '^gd$'; then
  echo "GD extension is missing for PHP $PHP_VERSION_SHORT."
  sudo apt-get update
  sudo apt-get install -y "php${PHP_VERSION_SHORT}-gd"
fi

php -m | grep -qiE '^gd$' || {
  echo "ERROR: PHP GD extension is still missing after installation."
  php --ini
  exit 1
}

echo "PHP GD extension is available."

# ====================================================
# LARAVEL
# ====================================================

echo "Installing Laravel dependencies..."

composer install \
  --no-dev \
  --prefer-dist \
  --optimize-autoloader \
  --no-interaction \
  --no-progress

echo "Running database migrations..."

php artisan migrate --force

# ====================================================
# FIX PERMISSIONS BEFORE ARTISAN CACHE COMMANDS
# ====================================================

echo "Fixing Laravel writable directories..."

sudo mkdir -p \
  storage/logs \
  storage/framework/cache \
  storage/framework/sessions \
  storage/framework/views \
  bootstrap/cache

sudo touch \
  storage/logs/laravel.log

sudo chown -R \
  "$USER":www-data \
  storage \
  bootstrap/cache

sudo find \
  storage \
  bootstrap/cache \
  -type d \
  -exec chmod 775 {} \;

sudo find \
  storage \
  bootstrap/cache \
  -type f \
  -exec chmod 664 {} \;

# ====================================================
# LARAVEL CACHE
# ====================================================

echo "Refreshing Laravel caches..."

php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# ====================================================
# PROPHET ENVIRONMENT
# ====================================================

echo "Preparing Prophet virtual environment..."

cd "$ML_DIR"

if [ ! -x venv/bin/python ]; then
  python3 -m venv venv
fi

./venv/bin/pip install \
  --upgrade pip

./venv/bin/pip install \
  -r requirements.txt

mkdir -p saved_models

# ====================================================
# UV + PYTHON 3.12 FOR CNN
# ====================================================

echo "Checking uv..."

UV="$HOME/.local/bin/uv"

if [ ! -x "$UV" ]; then
  echo "uv is not installed. Installing uv..."
  curl -LsSf https://astral.sh/uv/install.sh | sh
fi

test -x "$UV" || {
  echo "ERROR: uv installation failed."
  exit 1
}

echo "uv version:"
"$UV" --version

echo "Ensuring Python 3.12 is available for CNN..."
"$UV" python install 3.12

# ====================================================
# CNN ENVIRONMENT
# ====================================================

echo "Preparing CNN virtual environment..."

CNN_PYTHON_VERSION=""

if [ -x cnn_venv/bin/python ]; then
  CNN_PYTHON_VERSION="$(
    ./cnn_venv/bin/python -c "import sys; print(str(sys.version_info.major) + chr(46) + str(sys.version_info.minor))"
  )"
fi

if [ "$CNN_PYTHON_VERSION" != "3.12" ]; then
  echo "CNN environment is missing or uses Python $CNN_PYTHON_VERSION."
  echo "Recreating CNN environment with Python 3.12..."

  rm -rf cnn_venv
  "$UV" venv --python 3.12 cnn_venv
fi

echo "CNN Python version:"
./cnn_venv/bin/python --version

echo "Installing CNN dependencies..."
"$UV" pip install \
  --python ./cnn_venv/bin/python \
  -r cnn_requirements.txt

mkdir -p models

# ====================================================
# CNN MODEL CHECK
# ====================================================

CNN_MODEL="$ML_DIR/models/bottle_classification_model.keras"
CNN_CLASSES="$ML_DIR/models/classnames.json"

if [ ! -f "$CNN_MODEL" ]; then
  echo "ERROR: CNN model is missing:"
  echo "$CNN_MODEL"
  exit 1
fi

if [ ! -f "$CNN_CLASSES" ]; then
  echo "ERROR: classnames.json is missing:"
  echo "$CNN_CLASSES"
  exit 1
fi

echo "Checking TensorFlow installation..."

./cnn_venv/bin/python -c \
  "import tensorflow as tf; print(tf.__version__)"

# ====================================================
# PROPHET SYSTEMD SERVICE
# ====================================================

echo "Installing Prophet service..."

sudo cp \
  "$APP_DIR/deployment/systemd/plink-prophet.service" \
  /etc/systemd/system/plink-prophet.service

# ====================================================
# CNN SYSTEMD SERVICE
# ====================================================

echo "Installing CNN service..."

sudo cp \
  "$APP_DIR/deployment/plink-cnn.service" \
  /etc/systemd/system/plink-cnn.service

# ====================================================
# SYSTEMD
# ====================================================

sudo systemctl daemon-reload

sudo systemctl enable \
  plink-prophet

sudo systemctl enable \
  plink-cnn

sudo systemctl restart \
  plink-prophet

sudo systemctl restart \
  plink-cnn

# ====================================================
# LARAVEL SCHEDULER
# ====================================================

echo "Installing Laravel scheduler cron..."

sudo cp \
  "$APP_DIR/deployment/cron/plink-scheduler" \
  /etc/cron.d/plink-scheduler

sudo chown \
  root:root \
  /etc/cron.d/plink-scheduler

sudo chmod \
  644 \
  /etc/cron.d/plink-scheduler

# ====================================================
# FINAL LARAVEL / NGINX
# ====================================================

cd "$APP_DIR"

php artisan queue:restart || true

sudo systemctl reload nginx

# ====================================================
# HEALTH CHECKS
# ====================================================

echo "Waiting for ML services..."

sleep 5

echo "Checking Prophet service..."

if ! curl \
  --fail \
  --silent \
  http://127.0.0.1:5001/health \
  >/dev/null; then

  echo "ERROR: Prophet health check failed."
  sudo systemctl status plink-prophet --no-pager || true
  sudo journalctl -u plink-prophet -n 50 --no-pager || true
  exit 1
fi

echo "Prophet is healthy."

echo "Checking CNN service..."

if ! curl \
  --fail \
  --silent \
  http://127.0.0.1:5002/health \
  >/dev/null; then

  echo "ERROR: CNN health check failed."
  sudo systemctl status plink-cnn --no-pager || true
  sudo journalctl -u plink-cnn -n 100 --no-pager || true
  exit 1
fi

echo "CNN is healthy."

# ====================================================
# LARAVEL ROUTE CHECK
# ====================================================

echo "Checking classification route..."

php artisan route:list \
  --path=iot/classify

echo "Deployment completed successfully."