import io
import json
import os
from pathlib import Path

import numpy as np
import tensorflow as tf
from flask import Flask, jsonify, request
from PIL import Image, UnidentifiedImageError

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

MAX_IMAGE_BYTES = int(
    os.getenv(
        "CNN_MAX_IMAGE_BYTES",
        str(5 * 1024 * 1024),
    )
)

SAVE_DEBUG_IMAGE = (
    os.getenv("CNN_SAVE_DEBUG_IMAGE", "false")
    .strip()
    .lower()
    in {"1", "true", "yes", "on"}
)

DEBUG_IMAGE_PATH = Path(
    os.getenv(
        "CNN_DEBUG_IMAGE_PATH",
        str(BASE_DIR / "last_esp32_capture.jpg"),
    )
)

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
        str(loaded_names[str(index)]).strip()
        for index in range(len(loaded_names))
    ]
elif isinstance(loaded_names, list):
    class_names = [str(name).strip() for name in loaded_names]
else:
    raise ValueError(
        "classnames.json must contain a JSON array or zero-based JSON object."
    )

if not class_names or any(not name for name in class_names):
    raise ValueError("classnames.json contains missing or empty class names.")

model = tf.keras.models.load_model(
    MODEL_PATH,
    compile=False,
)

# Read the required image size directly from the saved model so the API
# automatically works with the current model (for example 224x224 MobileNetV2).
input_shape = model.input_shape

if isinstance(input_shape, list):
    raise ValueError("Expected one image input, but the model has multiple inputs.")

if len(input_shape) != 4:
    raise ValueError(f"Unexpected model input shape: {input_shape}")

IMAGE_HEIGHT = int(input_shape[1])
IMAGE_WIDTH = int(input_shape[2])
IMAGE_CHANNELS = int(input_shape[3])

if IMAGE_CHANNELS != 3:
    raise ValueError(
        f"Expected a 3-channel RGB model, but the model expects {IMAGE_CHANNELS} channels."
    )

output_shape = model.output_shape

if isinstance(output_shape, list):
    raise ValueError("Expected one classification output, but the model has multiple outputs.")

MODEL_CLASS_COUNT = int(output_shape[-1])

if MODEL_CLASS_COUNT != len(class_names):
    raise ValueError(
        "Model/class-name mismatch.\n"
        f"Model outputs: {MODEL_CLASS_COUNT}\n"
        f"classnames.json: {len(class_names)}"
    )

print("=" * 60)
print("PLink CNN API")
print(f"Model: {MODEL_PATH}")
print(f"Class names: {CLASS_NAMES_PATH}")
print(f"Input size: {IMAGE_WIDTH}x{IMAGE_HEIGHT}")
print(f"Classes: {MODEL_CLASS_COUNT}")
print("Class order:")
for index, name in enumerate(class_names):
    print(f"  {index}: {name}")
print("=" * 60)


@app.get("/health")
def health():
    return jsonify(
        {
            "success": True,
            "service": "PLink CNN API",
            "model": MODEL_PATH.name,
            "classes": class_names,
            "class_count": len(class_names),
            "input_size": [IMAGE_WIDTH, IMAGE_HEIGHT],
        }
    )


@app.post("/predict")
def predict():
    if not request.data:
        return jsonify(
            {
                "success": False,
                "message": "JPEG body is empty.",
            }
        ), 400

    if len(request.data) > MAX_IMAGE_BYTES:
        return jsonify(
            {
                "success": False,
                "message": "JPEG exceeds maximum allowed image size.",
                "max_bytes": MAX_IMAGE_BYTES,
            }
        ), 413

    try:
        image = Image.open(
            io.BytesIO(request.data)
        ).convert("RGB")

        if SAVE_DEBUG_IMAGE:
            DEBUG_IMAGE_PATH.parent.mkdir(
                parents=True,
                exist_ok=True,
            )
            image.save(
                DEBUG_IMAGE_PATH,
                format="JPEG",
                quality=95,
            )
            print(f"Saved ESP32 capture: {DEBUG_IMAGE_PATH}")

        image = image.resize(
            (IMAGE_WIDTH, IMAGE_HEIGHT),
            Image.Resampling.BILINEAR,
        )

        image_array = np.asarray(
            image,
            dtype=np.float32,
        )

        # IMPORTANT:
        # Do NOT divide by 255 here.
        #
        # Your saved PLink models contain their preprocessing/rescaling
        # inside the model itself. Feeding 0..255 RGB values here prevents
        # the image from being normalized twice.
        batch = np.expand_dims(
            image_array,
            axis=0,
        )

        predictions = model.predict(
            batch,
            verbose=0,
        )[0]

        predictions = np.asarray(
            predictions,
            dtype=np.float32,
        )

        if predictions.ndim != 1:
            raise ValueError(
                f"Unexpected prediction shape: {predictions.shape}"
            )

        if len(predictions) != len(class_names):
            raise ValueError(
                "Prediction/class-name mismatch: "
                f"{len(predictions)} model scores vs "
                f"{len(class_names)} class names."
            )

        class_index = int(
            np.argmax(predictions)
        )

        confidence = float(
            predictions[class_index]
        )

        predicted_class = class_names[
            class_index
        ]

        # Return every category score, sorted highest to lowest.
        sorted_indices = np.argsort(
            predictions
        )[::-1]

        all_predictions = []

        for index in sorted_indices:
            index = int(index)
            probability = float(
                predictions[index]
            )

            all_predictions.append(
                {
                    "class": class_names[index],
                    "class_index": index,
                    "confidence": probability,
                    "confidence_percent": round(
                        probability * 100.0,
                        2,
                    ),
                }
            )

        print()
        print("=" * 60)
        print(f"Prediction: {predicted_class}")
        print(f"Winner confidence: {confidence * 100.0:.2f}%")
        print("All CNN scores:")

        for item in all_predictions:
            print(
                f"  {item['class']}: "
                f"{item['confidence_percent']:.2f}%"
            )

        print("=" * 60)

        return jsonify(
            {
                "success": True,
                "class": predicted_class,
                "label": predicted_class,
                "class_index": class_index,
                "confidence": confidence,
                "confidence_percent": round(
                    confidence * 100.0,
                    2,
                ),
                "predictions": all_predictions,
            }
        )

    except UnidentifiedImageError:
        return jsonify(
            {
                "success": False,
                "message": "Request body is not a valid image.",
            }
        ), 422

    except Exception as exc:
        app.logger.exception(
            "CNN prediction failed"
        )

        return jsonify(
            {
                "success": False,
                "message": str(exc),
            }
        ), 500


if __name__ == "__main__":
    app.run(
        host="127.0.0.1",
        port=5002,
        debug=False,
    )
