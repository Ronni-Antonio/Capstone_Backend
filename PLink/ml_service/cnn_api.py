import io
import json
import os
from pathlib import Path

import numpy as np
import tensorflow as tf
from flask import Flask, jsonify, request
from PIL import Image, UnidentifiedImageError


# ============================================================
# PATH CONFIGURATION
# ============================================================

BASE_DIR = Path(__file__).resolve().parent

MODEL_PATH = Path(
    os.getenv(
        "CNN_MODEL_PATH",
        str(
            BASE_DIR
            / "models"
            / "bottle_classification_model.keras"
        ),
    )
)

CLASS_NAMES_PATH = Path(
    os.getenv(
        "CNN_CLASS_NAMES_PATH",
        str(
            BASE_DIR
            / "models"
            / "classnames.json"
        ),
    )
)

MAX_IMAGE_BYTES = int(
    os.getenv(
        "CNN_MAX_IMAGE_BYTES",
        str(5 * 1024 * 1024),
    )
)

SAVE_DEBUG_IMAGE = (
    os.getenv(
        "CNN_SAVE_DEBUG_IMAGE",
        "false"
    ).lower()
    in {"1", "true", "yes", "on"}
)

DEBUG_IMAGE_PATH = BASE_DIR / "last_esp32_capture.jpg"


# ============================================================
# FLASK
# ============================================================

app = Flask(__name__)


# ============================================================
# CHECK MODEL FILES
# ============================================================

if not MODEL_PATH.exists():
    raise FileNotFoundError(
        f"CNN model was not found at:\n"
        f"{MODEL_PATH}"
    )

if not CLASS_NAMES_PATH.exists():
    raise FileNotFoundError(
        f"CNN class-name file was not found at:\n"
        f"{CLASS_NAMES_PATH}"
    )


# ============================================================
# LOAD CLASS NAMES
# ============================================================

with CLASS_NAMES_PATH.open(
    "r",
    encoding="utf-8"
) as file:
    loaded_names = json.load(file)


# Supports:
#
# [
#   "Crushed or Dirty",
#   "HDPE Category",
#   ...
# ]
#
# OR
#
# {
#   "0": "Crushed or Dirty",
#   "1": "HDPE Category"
# }
#
if isinstance(loaded_names, dict):

    class_names = [
        str(
            loaded_names[str(index)]
        ).strip()
        for index in range(
            len(loaded_names)
        )
    ]

elif isinstance(
    loaded_names,
    list
):

    class_names = [
        str(name).strip()
        for name in loaded_names
    ]

else:

    raise ValueError(
        "classnames.json must contain "
        "a JSON array or a zero-based "
        "JSON object."
    )


if not class_names:
    raise ValueError(
        "classnames.json contains no classes."
    )


# ============================================================
# LOAD CNN MODEL
# ============================================================

print("\n======================================")
print("Loading PLink CNN model")
print("======================================")

print(
    f"Model:\n{MODEL_PATH}"
)

print(
    f"\nClasses:\n{CLASS_NAMES_PATH}"
)

model = tf.keras.models.load_model(
    MODEL_PATH,
    compile=False
)


# ============================================================
# DETECT MODEL INPUT SIZE AUTOMATICALLY
# ============================================================

input_shape = model.input_shape

if isinstance(input_shape, list):
    raise ValueError(
        "CNN model has multiple inputs. "
        "Expected one image input."
    )


# Expected:
#
# (None, 224, 224, 3)
#
IMAGE_HEIGHT = int(
    input_shape[1]
)

IMAGE_WIDTH = int(
    input_shape[2]
)

CHANNELS = int(
    input_shape[3]
)


if CHANNELS != 3:
    raise ValueError(
        f"Expected RGB model with 3 channels, "
        f"but model expects {CHANNELS}."
    )


# ============================================================
# CHECK OUTPUT CLASS COUNT
# ============================================================

output_shape = model.output_shape

if isinstance(output_shape, list):
    raise ValueError(
        "CNN model has multiple outputs. "
        "Expected one classification output."
    )

model_class_count = int(
    output_shape[-1]
)

if model_class_count != len(
    class_names
):
    raise ValueError(
        "Model/class-name mismatch.\n"
        f"Model outputs: {model_class_count}\n"
        f"classnames.json: {len(class_names)}"
    )


print(
    f"\nInput size: "
    f"{IMAGE_WIDTH}x{IMAGE_HEIGHT}"
)

print(
    f"Number of classes: "
    f"{len(class_names)}"
)

print("\nClass order:")

for index, name in enumerate(
    class_names
):
    print(
        f"{index}: {name}"
    )

print("======================================\n")


# ============================================================
# HEALTH CHECK
# ============================================================

@app.get("/health")
def health():

    return jsonify(
        {
            "success": True,
            "service": "PLink CNN API",

            "model": (
                MODEL_PATH.name
            ),

            "classes": (
                class_names
            ),

            "class_count": (
                len(class_names)
            ),

            "input_size": [
                IMAGE_WIDTH,
                IMAGE_HEIGHT,
            ],
        }
    )


# ============================================================
# PREDICT
# ============================================================

@app.post("/predict")
def predict():

    # --------------------------------------------------------
    # Validate request
    # --------------------------------------------------------

    if not request.data:

        return jsonify(
            {
                "success": False,
                "message": (
                    "JPEG body is empty."
                ),
            }
        ), 400


    if len(request.data) > MAX_IMAGE_BYTES:

        return jsonify(
            {
                "success": False,
                "message": (
                    "JPEG exceeds maximum "
                    "allowed image size."
                ),
            }
        ), 413


    try:

        # ----------------------------------------------------
        # Decode JPEG
        # ----------------------------------------------------

        image = Image.open(
            io.BytesIO(
                request.data
            )
        )

        image = image.convert(
            "RGB"
        )


        # ----------------------------------------------------
        # Optional debugging
        # ----------------------------------------------------

        if SAVE_DEBUG_IMAGE:

            image.save(
                DEBUG_IMAGE_PATH,
                format="JPEG",
                quality=95,
            )

            print(
                f"Saved ESP32 capture to: "
                f"{DEBUG_IMAGE_PATH}"
            )


        # ----------------------------------------------------
        # Resize exactly to model dimensions
        # ----------------------------------------------------

        image = image.resize(
            (
                IMAGE_WIDTH,
                IMAGE_HEIGHT
            ),
            Image.Resampling.BILINEAR,
        )


        # ----------------------------------------------------
        # Convert to NumPy
        # ----------------------------------------------------

        image_array = np.asarray(
            image,
            dtype=np.float32,
        )


        # ====================================================
        # IMPORTANT
        # ====================================================
        #
        # DO NOT:
        #
        #     image_array /= 255.0
        #
        # DO NOT:
        #
        #     preprocess_input(image_array)
        #
        # Your revised MobileNetV2 model already contains
        # its preprocessing layer.
        #
        # The model therefore receives normal pixel values:
        #
        #     0 ... 255
        #
        # ====================================================


        batch = np.expand_dims(
            image_array,
            axis=0
        )


        # ----------------------------------------------------
        # Prediction
        # ----------------------------------------------------

        predictions = model.predict(
            batch,
            verbose=0
        )[0]


        predictions = np.asarray(
            predictions,
            dtype=np.float32,
        )


        # ----------------------------------------------------
        # Winner
        # ----------------------------------------------------

        class_index = int(
            np.argmax(
                predictions
            )
        )


        confidence = float(
            predictions[
                class_index
            ]
        )


        if class_index >= len(
            class_names
        ):

            return jsonify(
                {
                    "success": False,

                    "message": (
                        "CNN output index "
                        f"{class_index} does not "
                        "exist in classnames.json."
                    ),
                }
            ), 500


        predicted_class = (
            class_names[
                class_index
            ]
        )


        # ----------------------------------------------------
        # All probabilities
        # ----------------------------------------------------

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
                    "class": (
                        class_names[
                            index
                        ]
                    ),

                    "class_index": (
                        index
                    ),

                    "confidence": (
                        probability
                    ),

                    "confidence_percent": (
                        round(
                            probability
                            * 100,
                            2
                        )
                    ),
                }
            )


        # ----------------------------------------------------
        # Console debugging
        # ----------------------------------------------------

        print(
            "\n======================================"
        )

        print(
            f"Prediction: "
            f"{predicted_class}"
        )

        print(
            f"Confidence: "
            f"{confidence * 100:.2f}%"
        )

        print(
            "\nTop 5 predictions:"
        )


        for result in all_predictions[:5]:

            print(
                f"{result['class']}: "
                f"{result['confidence_percent']:.2f}%"
            )


        print(
            "======================================\n"
        )


        # ----------------------------------------------------
        # Return JSON to Laravel
        # ----------------------------------------------------

        return jsonify(
            {
                "success": True,

                # Laravel reads this
                "class": (
                    predicted_class
                ),

                # Optional alias
                "label": (
                    predicted_class
                ),

                "class_index": (
                    class_index
                ),

                # Laravel reads this
                "confidence": (
                    confidence
                ),

                "confidence_percent": (
                    round(
                        confidence * 100,
                        2
                    )
                ),

                # Useful for debugging
                "predictions": (
                    all_predictions
                ),
            }
        )


    except UnidentifiedImageError:

        return jsonify(
            {
                "success": False,
                "message": (
                    "Request body is not "
                    "a valid image."
                ),
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


# ============================================================
# START SERVER
# ============================================================

if __name__ == "__main__":

    print(
        "Starting PLink CNN API..."
    )

    print(
        "URL: http://127.0.0.1:5002"
    )

    print(
        "Health: "
        "http://127.0.0.1:5002/health"
    )

    print(
        "Predict: "
        "http://127.0.0.1:5002/predict"
    )

    app.run(
        host="127.0.0.1",
        port=5002,
        debug=False
    )