import io
import json
import os
from pathlib import Path

import numpy as np
import tensorflow as tf
from flask import Flask, jsonify, request
from PIL import Image

BASE_DIR = Path(__file__).resolve().parent

MODEL_PATH = Path(
    os.getenv(
        "CNN_MODEL_PATH",
        str(BASE_DIR / "models" / "bottle_classification_model.keras"),
    )
)

CLASS_NAMES_PATH = Path(
    os.getenv(
        "CNN_CLASS_NAMES_PATH",
        str(BASE_DIR / "models" / "classnames.json"),
    )
)

IMAGE_WIDTH = int(os.getenv("CNN_IMAGE_WIDTH", "255"))
IMAGE_HEIGHT = int(os.getenv("CNN_IMAGE_HEIGHT", "255"))

app = Flask(__name__)

if not MODEL_PATH.exists():
    raise FileNotFoundError(
        f"CNN model was not found at {MODEL_PATH}. "
        "Set CNN_MODEL_PATH or place the .keras model in ml_service/models/."
    )

if not CLASS_NAMES_PATH.exists():
    raise FileNotFoundError(
        f"CNN class-name file was not found at {CLASS_NAMES_PATH}. "
        "Create classnames.json in ml_service/models/ using the exact training class order."
    )

with CLASS_NAMES_PATH.open("r", encoding="utf-8") as file:
    loaded_names = json.load(file)

# Accept either ["PET", "HDPE", ...] or {"0": "PET", "1": "HDPE", ...}.
if isinstance(loaded_names, dict):
    class_names = [
        loaded_names[str(index)]
        for index in range(len(loaded_names))
    ]
elif isinstance(loaded_names, list):
    class_names = [str(name) for name in loaded_names]
else:
    raise ValueError("classnames.json must contain a JSON array or zero-based JSON object.")

model = tf.keras.models.load_model(MODEL_PATH)


@app.get("/health")
def health():
    return jsonify(
        {
            "success": True,
            "service": "PLink CNN API",
            "model": MODEL_PATH.name,
            "classes": class_names,
            "input_size": [IMAGE_WIDTH, IMAGE_HEIGHT],
        }
    )


@app.post("/predict")
def predict():
    if not request.data:
        return jsonify({"success": False, "message": "JPEG body is empty."}), 400

    try:
        image = Image.open(io.BytesIO(request.data)).convert("RGB")
        image = image.resize((IMAGE_WIDTH, IMAGE_HEIGHT))

        image_array = np.asarray(image, dtype=np.float32)

        # This matches the existing PLink CNN training pipeline where images
        # were normalized with image / 255.0 before model.fit().
        image_array /= 255.0

        batch = np.expand_dims(image_array, axis=0)
        predictions = model.predict(batch, verbose=0)[0]

        class_index = int(np.argmax(predictions))
        confidence = float(predictions[class_index])

        if class_index >= len(class_names):
            return (
                jsonify(
                    {
                        "success": False,
                        "message": (
                            f"Model predicted output index {class_index}, but "
                            f"classnames.json only has {len(class_names)} entries."
                        ),
                    }
                ),
                500,
            )

        return jsonify(
            {
                "success": True,
                "class": class_names[class_index],
                "class_index": class_index,
                "confidence": confidence,
            }
        )

    except Exception as exc:
        app.logger.exception("CNN prediction failed")
        return jsonify({"success": False, "message": str(exc)}), 500


if __name__ == "__main__":
    app.run(host="127.0.0.1", port=5002, debug=False)
