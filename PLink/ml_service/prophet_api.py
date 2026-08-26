from flask import Flask, jsonify, request
from prophet import Prophet
import pandas as pd

app = Flask(__name__)

COUNT_SERIES = {
    "recycling_volume",
    "student_participation",
    "reward_redemptions",
}
FULLNESS_SERIES = {
    "plastic_fullness",
    "paper_fullness",
}


def forecast_one(name, rows, periods):
    if not isinstance(rows, list) or len(rows) < 2:
        return {
            "history": rows or [],
            "forecast": [],
            "warning": "At least two historical observations are required.",
        }

    frame = pd.DataFrame(rows)
    if "ds" not in frame.columns or "y" not in frame.columns:
        return {
            "history": rows,
            "forecast": [],
            "warning": "Series must contain ds and y fields.",
        }

    frame["ds"] = pd.to_datetime(frame["ds"], errors="coerce")
    frame["y"] = pd.to_numeric(frame["y"], errors="coerce")
    frame = frame.dropna(subset=["ds", "y"]).sort_values("ds")

    if len(frame) < 2:
        return {
            "history": rows,
            "forecast": [],
            "warning": "Not enough valid historical observations after cleaning.",
        }

    model = Prophet(
        daily_seasonality=False,
        weekly_seasonality=True,
        yearly_seasonality=False,
        interval_width=0.80,
    )
    model.fit(frame[["ds", "y"]])

    future = model.make_future_dataframe(periods=periods, freq="D")
    predicted = model.predict(future).tail(periods)

    points = []
    for _, row in predicted.iterrows():
        yhat = float(row["yhat"])
        lower = float(row["yhat_lower"])
        upper = float(row["yhat_upper"])

        if name in COUNT_SERIES:
            yhat = max(0.0, yhat)
            lower = max(0.0, lower)
            upper = max(0.0, upper)

        if name in FULLNESS_SERIES:
            yhat = min(100.0, max(0.0, yhat))
            lower = min(100.0, max(0.0, lower))
            upper = min(100.0, max(0.0, upper))

        points.append({
            "ds": row["ds"].strftime("%Y-%m-%d"),
            "yhat": round(yhat, 2),
            "yhat_lower": round(lower, 2),
            "yhat_upper": round(upper, 2),
        })

    clean_history = [
        {"ds": row.ds.strftime("%Y-%m-%d"), "y": round(float(row.y), 2)}
        for row in frame.itertuples(index=False)
    ]

    return {
        "history": clean_history,
        "forecast": points,
    }


@app.get("/health")
def health():
    return jsonify({"status": "ok", "service": "prophet"})


@app.post("/forecast")
def forecast():
    body = request.get_json(silent=True) or {}
    periods = int(body.get("periods", 7))
    periods = max(1, min(periods, 30))
    series = body.get("series", {})

    forecasts = {
        name: forecast_one(name, rows, periods)
        for name, rows in series.items()
    }

    return jsonify({
        "periods": periods,
        "forecasts": forecasts,
    })


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5001, debug=False)
