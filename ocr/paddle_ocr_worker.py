"""Local PaddleOCR worker. Reads one image path and emits JSON to stdout."""

from __future__ import annotations

import argparse
import contextlib
import json
import os
import sys
import tempfile
from pathlib import Path

# PaddlePaddle 3.x can select an unsupported oneDNN PIR path on Windows.
os.environ.setdefault("FLAGS_use_mkldnn", "0")
os.environ.setdefault("PADDLE_PDX_DISABLE_MODEL_SOURCE_CHECK", "True")
if sys.platform != "win32":
    # Keep downloaded models in the application layer, not the builder's HOME.
    os.environ.setdefault("PADDLE_PDX_CACHE_HOME", str(Path(__file__).resolve().parent / ".models"))

# Third-party libraries may print startup messages. stdout is reserved for JSON.
with contextlib.redirect_stdout(sys.stderr):
    import cv2
    import numpy as np
    from PIL import Image, ImageEnhance, ImageOps


def preprocess(source: Path, max_side: int = 2400) -> Path:
    with Image.open(source) as opened:
        image = ImageOps.exif_transpose(opened).convert("RGB")
        if max(image.size) > max_side:
            image.thumbnail((max_side, max_side), Image.Resampling.LANCZOS)

        gray = np.asarray(image.convert("L"))
        contrast = float(gray.std())
        if contrast < 45:
            image = ImageEnhance.Contrast(image).enhance(1.25)

        array = cv2.cvtColor(np.asarray(image), cv2.COLOR_RGB2BGR)
        if array.shape[0] >= 600 and array.shape[1] >= 600:
            array = cv2.bilateralFilter(array, 5, 35, 35)

        handle = tempfile.NamedTemporaryFile(suffix=".png", delete=False)
        handle.close()
        if not cv2.imwrite(handle.name, array):
            raise RuntimeError("Unable to prepare the uploaded document for OCR.")
        return Path(handle.name)


def as_lines(result: object) -> list[dict]:
    lines: list[dict] = []
    pages = result if isinstance(result, list) else [result]
    for page in pages:
        data = getattr(page, "json", None)
        data = data() if callable(data) else data
        if isinstance(data, str):
            data = json.loads(data)
        if isinstance(data, dict) and "res" in data:
            data = data["res"]
        if isinstance(data, dict):
            texts = data.get("rec_texts", [])
            scores = data.get("rec_scores", [])
            boxes = data.get("rec_polys", data.get("dt_polys", []))
            for index, text in enumerate(texts):
                if str(text).strip():
                    box = boxes[index] if index < len(boxes) else None
                    if hasattr(box, "tolist"):
                        box = box.tolist()
                    lines.append({
                        "text": str(text).strip(),
                        "confidence": float(scores[index]) if index < len(scores) else None,
                        "bounding_box": box,
                    })
    return lines


def scan(source: Path, profile: str = "document") -> dict:
    prepared = preprocess(source, 1280 if profile == "plate" else 2400)
    try:
        from paddleocr import PaddleOCR

        # Vehicle plates do not need a full document model. Keep document behaviour
        # unchanged, and explicitly select mobile models for the short Latin plate text.
        plate_options = {
            "ocr_version": "PP-OCRv4",
            "text_detection_model_name": "PP-OCRv4_mobile_det",
            "text_recognition_model_name": "en_PP-OCRv4_mobile_rec",
            "text_det_limit_type": "max",
            "text_det_limit_side_len": 1280,
            "text_recognition_batch_size": 1,
        } if profile == "plate" else {}
        engine = PaddleOCR(
            lang="en",
            device="cpu",
            cpu_threads=2,
            enable_mkldnn=False,
            use_doc_orientation_classify=profile != "plate",
            use_doc_unwarping=False,
            use_textline_orientation=True,
            **plate_options,
        )
        lines = as_lines(engine.predict(str(prepared)))
        return {"full_text": "\n".join(line["text"] for line in lines), "lines": lines}
    finally:
        prepared.unlink(missing_ok=True)


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("image")
    parser.add_argument("--profile", choices=("document", "plate"), default="document")
    args = parser.parse_args()
    source = Path(args.image).resolve()
    if not source.is_file():
        raise ValueError("Uploaded document is not available.")

    with contextlib.redirect_stdout(sys.stderr):
        payload = scan(source, args.profile)
    print(json.dumps(payload))
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except Exception as exception:
        print(json.dumps({"error": str(exception)}), file=sys.stderr)
        raise SystemExit(1)
