import io
import json
import os
from pathlib import Path

import numpy as np
import tensorflow as tf
from flask import Flask, jsonify, request
from PIL import Image, UnidentifiedImageError

BASE_DIR = Path(__file__).resolve().parent
MODELS_DIR = BASE_DIR / "models"

ROUTE_MODEL_PATH = Path(
    os.getenv(
        "CNN_ROUTE_MODEL_PATH",
        str(MODELS_DIR / "route_classifier.keras"),
    )
)

ROUTE_CLASS_NAMES_PATH = Path(
    os.getenv(
        "CNN_ROUTE_CLASS_NAMES_PATH",
        str(MODELS_DIR / "route_classnames.json"),
    )
)

PLASTIC_MODEL_PATH = Path(
    os.getenv(
        "CNN_PLASTIC_MODEL_PATH",
        str(MODELS_DIR / "plastic_type_classifier.keras"),
    )
)

PLASTIC_CLASS_NAMES_PATH = Path(
    os.getenv(
        "CNN_PLASTIC_CLASS_NAMES_PATH",
        str(MODELS_DIR / "plastic_classnames.json"),
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


def load_class_names(path: Path, display_name: str) -> list[str]:
    if not path.exists():
        raise FileNotFoundError(
            f"{display_name} class-name file was not found at {path}."
        )

    with path.open("r", encoding="utf-8") as file:
        loaded = json.load(file)

    if isinstance(loaded, dict):
        names = [
            str(loaded[str(index)]).strip()
            for index in range(len(loaded))
        ]
    elif isinstance(loaded, list):
        names = [str(name).strip() for name in loaded]
    else:
        raise ValueError(
            f"{path.name} must contain a JSON array or zero-based JSON object."
        )

    if not names or any(not name for name in names):
        raise ValueError(f"{path.name} contains missing or empty class names.")

    return names


def load_model_and_validate(
    model_path: Path,
    class_names: list[str],
    display_name: str,
):
    if not model_path.exists():
        raise FileNotFoundError(
            f"{display_name} model was not found at {model_path}."
        )

    model = tf.keras.models.load_model(
        model_path,
        compile=False,
    )

    input_shape = model.input_shape
    if isinstance(input_shape, list):
        raise ValueError(
            f"{display_name} must have exactly one image input."
        )

    if len(input_shape) != 4:
        raise ValueError(
            f"Unexpected {display_name} input shape: {input_shape}"
        )

    channels = int(input_shape[3])
    if channels != 3:
        raise ValueError(
            f"{display_name} expects {channels} channels; RGB (3) is required."
        )

    output_shape = model.output_shape
    if isinstance(output_shape, list):
        raise ValueError(
            f"{display_name} must have exactly one classification output."
        )

    output_count = int(output_shape[-1])
    if output_count != len(class_names):
        raise ValueError(
            f"{display_name} model/class-name mismatch: "
            f"model outputs {output_count}, but {len(class_names)} labels were loaded."
        )

    return model, input_shape


def normalize_route_name(value: str) -> str:
    return str(value).strip().lower()


route_class_names = load_class_names(
    ROUTE_CLASS_NAMES_PATH,
    "Stage 1 route",
)
plastic_class_names = load_class_names(
    PLASTIC_CLASS_NAMES_PATH,
    "Stage 2 plastic",
)

route_model, route_input_shape = load_model_and_validate(
    ROUTE_MODEL_PATH,
    route_class_names,
    "Stage 1 route",
)
plastic_model, plastic_input_shape = load_model_and_validate(
    PLASTIC_MODEL_PATH,
    plastic_class_names,
    "Stage 2 plastic",
)

ROUTE_IMAGE_HEIGHT = int(route_input_shape[1])
ROUTE_IMAGE_WIDTH = int(route_input_shape[2])
PLASTIC_IMAGE_HEIGHT = int(plastic_input_shape[1])
PLASTIC_IMAGE_WIDTH = int(plastic_input_shape[2])

if (
    ROUTE_IMAGE_HEIGHT != PLASTIC_IMAGE_HEIGHT
    or ROUTE_IMAGE_WIDTH != PLASTIC_IMAGE_WIDTH
):
    raise ValueError(
        "The Stage 1 and Stage 2 models must use the same input size. "
        f"Stage 1: {ROUTE_IMAGE_WIDTH}x{ROUTE_IMAGE_HEIGHT}; "
        f"Stage 2: {PLASTIC_IMAGE_WIDTH}x{PLASTIC_IMAGE_HEIGHT}."
    )

IMAGE_HEIGHT = ROUTE_IMAGE_HEIGHT
IMAGE_WIDTH = ROUTE_IMAGE_WIDTH

normalized_route_classes = [
    normalize_route_name(name)
    for name in route_class_names
]

if "paper" not in normalized_route_classes or "plastic" not in normalized_route_classes:
    raise ValueError(
        "route_classnames.json must contain both 'paper' and 'plastic'."
    )

print("=" * 68)
print("PLink TWO-STAGE CNN API")
print(f"Stage 1 model: {ROUTE_MODEL_PATH}")
print(f"Stage 1 labels: {ROUTE_CLASS_NAMES_PATH}")
print(f"Stage 2 model: {PLASTIC_MODEL_PATH}")
print(f"Stage 2 labels: {PLASTIC_CLASS_NAMES_PATH}")
print(f"Input size: {IMAGE_WIDTH}x{IMAGE_HEIGHT}")
print("Stage 1 route classes:")
for index, name in enumerate(route_class_names):
    print(f"  {index}: {name}")
print("Stage 2 plastic classes:")
for index, name in enumerate(plastic_class_names):
    print(f"  {index}: {name}")
print("=" * 68)


def sorted_prediction_rows(
    probabilities: np.ndarray,
    class_names: list[str],
) -> list[dict]:
    sorted_indices = np.argsort(probabilities)[::-1]
    rows = []

    for raw_index in sorted_indices:
        index = int(raw_index)
        probability = float(probabilities[index])

        rows.append(
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

    return rows


def predict_model(
    model,
    batch: np.ndarray,
    class_names: list[str],
):
    probabilities = model.predict(
        batch,
        verbose=0,
    )[0]

    probabilities = np.asarray(
        probabilities,
        dtype=np.float32,
    )

    if probabilities.ndim != 1:
        raise ValueError(
            f"Unexpected prediction shape: {probabilities.shape}"
        )

    if len(probabilities) != len(class_names):
        raise ValueError(
            "Prediction/class-name mismatch: "
            f"{len(probabilities)} scores vs {len(class_names)} labels."
        )

    class_index = int(np.argmax(probabilities))
    confidence = float(probabilities[class_index])
    class_name = class_names[class_index]

    return (
        class_name,
        class_index,
        confidence,
        sorted_prediction_rows(
            probabilities,
            class_names,
        ),
    )


@app.get("/health")
def health():
    return jsonify(
        {
            "success": True,
            "service": "PLink Two-Stage CNN API",
            "input_size": [IMAGE_WIDTH, IMAGE_HEIGHT],
            "stage_1": {
                "name": "route_classifier",
                "model": ROUTE_MODEL_PATH.name,
                "class_names_file": ROUTE_CLASS_NAMES_PATH.name,
                "classes": route_class_names,
                "class_count": len(route_class_names),
            },
            "stage_2": {
                "name": "plastic_type_classifier",
                "model": PLASTIC_MODEL_PATH.name,
                "class_names_file": PLASTIC_CLASS_NAMES_PATH.name,
                "classes": plastic_class_names,
                "class_count": len(plastic_class_names),
            },
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

        # Both saved models own their MobileNetV2 rescaling. Keep inference
        # values in RGB 0..255 and do NOT call preprocess_input here.
        batch = np.expand_dims(
            image_array,
            axis=0,
        )

        (
            route_class,
            route_class_index,
            route_confidence,
            route_predictions,
        ) = predict_model(
            route_model,
            batch,
            route_class_names,
        )

        route = normalize_route_name(
            route_class
        )

        if route not in {"paper", "plastic"}:
            raise ValueError(
                f"Unexpected Stage 1 route class: {route_class!r}. "
                "Expected paper or plastic."
            )

        # Paper needs no second classifier. Preserve the legacy `class` and
        # `label` fields expected by Laravel by returning the existing paper
        # source label.
        if route == "paper":
            predicted_class = "White Papers Category"
            class_index = None
            confidence = route_confidence
            subtype_predictions = []
            plastic_type = None
            plastic_type_confidence = None

        else:
            (
                predicted_class,
                class_index,
                confidence,
                subtype_predictions,
            ) = predict_model(
                plastic_model,
                batch,
                plastic_class_names,
            )

            plastic_type = predicted_class
            plastic_type_confidence = confidence

        print()
        print("=" * 68)
        print("TWO-STAGE CLASSIFICATION")
        print(
            f"Stage 1 route: {route} "
            f"({route_confidence * 100.0:.2f}%)"
        )
        print("Stage 1 scores:")
        for item in route_predictions:
            print(
                f"  {item['class']}: "
                f"{item['confidence_percent']:.2f}%"
            )

        if route == "plastic":
            print(
                f"Stage 2 plastic type: {predicted_class} "
                f"({confidence * 100.0:.2f}%)"
            )
            print("Stage 2 scores:")
            for item in subtype_predictions:
                print(
                    f"  {item['class']}: "
                    f"{item['confidence_percent']:.2f}%"
                )
        else:
            print("Stage 2 skipped because Stage 1 predicted paper.")

        print("=" * 68)

        # Compatibility contract:
        # - `class`, `label`, `confidence`, `predictions` remain available to
        #   the existing Laravel/ESP32 pipeline.
        # - new route/plastic_type fields expose the two-stage result.
        return jsonify(
            {
                "success": True,
                "pipeline": "two_stage",
                "route": route,
                "route_raw_prediction": route,
                "route_class_index": route_class_index,
                "route_confidence": route_confidence,
                "route_confidence_percent": round(
                    route_confidence * 100.0,
                    2,
                ),
                "route_predictions": route_predictions,
                "class": predicted_class,
                "label": predicted_class,
                "class_index": class_index,
                "confidence": confidence,
                "confidence_percent": round(
                    confidence * 100.0,
                    2,
                ),
                "plastic_type": plastic_type,
                "plastic_type_confidence": plastic_type_confidence,
                "plastic_type_confidence_percent": (
                    round(plastic_type_confidence * 100.0, 2)
                    if plastic_type_confidence is not None
                    else None
                ),
                "predictions": subtype_predictions,
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
            "Two-stage CNN prediction failed"
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
