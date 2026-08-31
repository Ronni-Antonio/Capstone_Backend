from __future__ import annotations

import json
from datetime import datetime, timedelta, timezone
from pathlib import Path
from zoneinfo import ZoneInfo

import pandas as pd
from flask import Flask, jsonify, request
from prophet import Prophet
from prophet.serialize import model_from_json, model_to_json

app = Flask(__name__)

BASE_DIR = Path(__file__).resolve().parent
MODEL_DIR = BASE_DIR / "saved_models"
MODEL_DIR.mkdir(parents=True, exist_ok=True)
MODEL_METADATA_FILE = MODEL_DIR / "metadata.json"

APP_TIMEZONE = ZoneInfo("Asia/Manila")
MAX_FORECAST_PERIODS = 90

SUPPORTED_MODELS = (
    "recycling_volume",
    "student_participation",
    "reward_redemptions",
    "plastic_fullness",
    "paper_fullness",
)

COUNT_MODELS = {
    "recycling_volume",
    "student_participation",
    "reward_redemptions",
}

FULLNESS_MODELS = {
    "plastic_fullness",
    "paper_fullness",
}


def get_model_path(prediction_type: str) -> Path:
    return MODEL_DIR / f"{prediction_type}.json"


def load_metadata() -> dict:
    if not MODEL_METADATA_FILE.exists():
        return {}

    try:
        return json.loads(MODEL_METADATA_FILE.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError):
        return {}


def save_metadata(metadata: dict) -> None:
    MODEL_METADATA_FILE.write_text(
        json.dumps(metadata, indent=2),
        encoding="utf-8",
    )


def prepare_dataframe(prediction_type: str, data: list[dict]) -> pd.DataFrame:
    if prediction_type not in SUPPORTED_MODELS:
        raise ValueError(f"Unsupported prediction type: {prediction_type}")

    if not isinstance(data, list):
        raise ValueError("Historical data must be an array.")

    if len(data) < 2:
        raise ValueError("At least two historical records are required.")

    df = pd.DataFrame(data)

    if "ds" not in df.columns or "y" not in df.columns:
        raise ValueError("Every historical record must contain ds and y.")

    df = df[["ds", "y"]].copy()
    df["ds"] = pd.to_datetime(df["ds"], errors="coerce").dt.tz_localize(None)
    df["y"] = pd.to_numeric(df["y"], errors="coerce")
    df = df.dropna(subset=["ds", "y"])

    if len(df) < 2:
        raise ValueError("Not enough valid historical records after cleaning.")

    # Laravel already sends one daily value, but this makes the service robust
    # if duplicate dates are accidentally supplied.
    aggregation = "mean" if prediction_type in FULLNESS_MODELS else "sum"
    df = df.groupby("ds", as_index=False)["y"].agg(aggregation).sort_values("ds")

    if df["ds"].nunique() < 2:
        raise ValueError("Prophet requires at least two distinct dates.")

    return df


def create_prophet_model() -> Prophet:
    # Auto seasonality prevents an overly complex weekly/yearly component when
    # the project still has only a small amount of historical school data.
    return Prophet(
        daily_seasonality=False,
        weekly_seasonality="auto",
        yearly_seasonality="auto",
        interval_width=0.80,
    )


def train_model(prediction_type: str, historical_data: list[dict]) -> dict:
    df = prepare_dataframe(prediction_type, historical_data)

    model = create_prophet_model()
    model.fit(df)

    model_path = get_model_path(prediction_type)
    model_path.write_text(model_to_json(model), encoding="utf-8")

    metadata = load_metadata()
    metadata[prediction_type] = {
        "trained_at": datetime.now(timezone.utc).isoformat(),
        "records": int(len(df)),
        "first_date": df["ds"].min().strftime("%Y-%m-%d"),
        "last_date": df["ds"].max().strftime("%Y-%m-%d"),
    }
    save_metadata(metadata)

    return metadata[prediction_type]


def load_saved_model(prediction_type: str) -> Prophet:
    if prediction_type not in SUPPORTED_MODELS:
        raise ValueError(f"Unsupported prediction type: {prediction_type}")

    model_path = get_model_path(prediction_type)

    if not model_path.exists():
        raise FileNotFoundError(
            f"No trained model exists for {prediction_type}. Train the models first."
        )

    return model_from_json(model_path.read_text(encoding="utf-8"))


def generate_forecast(prediction_type: str, periods: int = 7) -> list[dict]:
    model = load_saved_model(prediction_type)
    periods = max(1, min(int(periods), MAX_FORECAST_PERIODS))

    if model.history is None or model.history.empty:
        raise ValueError(f"Saved model {prediction_type} has no training history.")

    last_training_date = pd.Timestamp(model.history["ds"].max()).normalize()
    tomorrow = pd.Timestamp(datetime.now(APP_TIMEZONE).date() + timedelta(days=1))

    # If a model was trained several days ago, extend prediction far enough to
    # still return a complete N-day forecast beginning tomorrow.
    forecast_start = max(tomorrow, last_training_date + pd.Timedelta(days=1))
    forecast_end = forecast_start + pd.Timedelta(days=periods - 1)
    future_periods = max(1, int((forecast_end - last_training_date).days))

    future = model.make_future_dataframe(periods=future_periods, freq="D")
    forecast = model.predict(future)

    future_forecast = forecast[
        (forecast["ds"] >= forecast_start) & (forecast["ds"] <= forecast_end)
    ].head(periods)

    predictions: list[dict] = []

    for _, row in future_forecast.iterrows():
        yhat = max(0.0, float(row["yhat"]))
        yhat_lower = max(0.0, float(row["yhat_lower"]))
        yhat_upper = max(0.0, float(row["yhat_upper"]))

        if prediction_type in FULLNESS_MODELS:
            yhat = min(100.0, yhat)
            yhat_lower = min(100.0, yhat_lower)
            yhat_upper = min(100.0, yhat_upper)
            yhat = round(yhat, 2)
            yhat_lower = round(yhat_lower, 2)
            yhat_upper = round(yhat_upper, 2)
        else:
            # Counts are displayed as whole items/students/redemptions.
            yhat = int(round(yhat))
            yhat_lower = int(round(yhat_lower))
            yhat_upper = int(round(yhat_upper))

        predictions.append(
            {
                "ds": row["ds"].strftime("%Y-%m-%d"),
                "yhat": yhat,
                "yhat_lower": yhat_lower,
                "yhat_upper": yhat_upper,
            }
        )

    return predictions


@app.get("/health")
def health():
    metadata = load_metadata()
    available_models = [
        model_name
        for model_name in SUPPORTED_MODELS
        if get_model_path(model_name).exists()
    ]

    return jsonify(
        {
            "success": True,
            "service": "PLink Prophet API",
            "status": "running",
            "available_models": available_models,
            "metadata": metadata,
        }
    )


@app.get("/models")
def models():
    metadata = load_metadata()

    result = {
        model_name: {
            "trained": get_model_path(model_name).exists(),
            "metadata": metadata.get(model_name),
        }
        for model_name in SUPPORTED_MODELS
    }

    return jsonify({"success": True, "models": result})


@app.post("/train-all")
def train_all():
    payload = request.get_json(silent=True) or {}
    datasets = payload.get("datasets", {})

    if not isinstance(datasets, dict) or not datasets:
        return jsonify(
            {
                "success": False,
                "message": "No datasets were provided.",
                "models": {},
                "errors": {},
            }
        ), 422

    results: dict = {}
    errors: dict = {}

    for prediction_type in SUPPORTED_MODELS:
        historical_data = datasets.get(prediction_type)

        if not historical_data:
            message = "No historical data supplied."
            results[prediction_type] = {"trained": False, "reason": message}
            errors[prediction_type] = message
            continue

        try:
            training_info = train_model(prediction_type, historical_data)
            results[prediction_type] = {"trained": True, **training_info}
        except Exception as exc:  # Keep other independent models trainable.
            message = str(exc)
            results[prediction_type] = {"trained": False, "error": message}
            errors[prediction_type] = message

    return jsonify(
        {
            "success": len(errors) == 0,
            "message": "Prophet training completed.",
            "models": results,
            "errors": errors,
        }
    )


@app.post("/forecast")
def forecast_one():
    payload = request.get_json(silent=True) or {}
    prediction_type = payload.get("prediction_type")

    try:
        periods = int(payload.get("periods", 7))
    except (TypeError, ValueError):
        return jsonify({"success": False, "message": "periods must be an integer."}), 422

    if prediction_type not in SUPPORTED_MODELS:
        return jsonify({"success": False, "message": "Invalid prediction type."}), 422

    try:
        predictions = generate_forecast(prediction_type, periods)
        return jsonify(
            {
                "success": True,
                "prediction_type": prediction_type,
                "periods": periods,
                "forecast": predictions,
            }
        )
    except Exception as exc:
        return jsonify({"success": False, "message": str(exc)}), 500


@app.post("/forecast-all")
def forecast_all():
    payload = request.get_json(silent=True) or {}

    try:
        periods = int(payload.get("periods", 7))
    except (TypeError, ValueError):
        return jsonify({"success": False, "message": "periods must be an integer."}), 422

    periods = max(1, min(periods, MAX_FORECAST_PERIODS))
    forecasts: dict = {}
    errors: dict = {}

    for prediction_type in SUPPORTED_MODELS:
        try:
            forecasts[prediction_type] = generate_forecast(prediction_type, periods)
        except Exception as exc:
            forecasts[prediction_type] = []
            errors[prediction_type] = str(exc)

    return jsonify(
        {
            "success": len(errors) == 0,
            "periods": periods,
            "forecasts": forecasts,
            "errors": errors,
        }
    )


if __name__ == "__main__":
    # Development only. Production uses gunicorn through systemd.
    app.run(host="127.0.0.1", port=5001, debug=False)
