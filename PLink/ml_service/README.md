# PLINK Prophet Service

Create a Python virtual environment, install dependencies, and run the service:

```bash
python -m venv .venv
# Windows
.venv\Scripts\activate
# macOS/Linux
# source .venv/bin/activate

pip install -r requirements.txt
python prophet_api.py
```

The default Laravel setting expects the service at `http://127.0.0.1:5001`.
Set `PROPHET_API_URL` in Laravel's `.env` if the Python service is deployed elsewhere.
