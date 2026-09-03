"""Run the real OCR worker with synthetic, non-personal data. Nonzero means unsafe to deploy."""

import json
import subprocess
import sys
import tempfile
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont


def validate_payload(stdout: str) -> None:
    payload = json.loads(stdout)
    if not isinstance(payload, dict) or not isinstance(payload.get("lines"), list):
        raise ValueError("OCR worker did not return the expected JSON object")
    lines = payload["lines"]
    if not lines or not all(isinstance(line, dict) and isinstance(line.get("text"), str) for line in lines):
        raise ValueError("OCR worker did not return text lines")
    if "GREENPOOL" not in " ".join(line["text"] for line in lines).upper():
        raise ValueError("OCR ran but could not read the synthetic GREENPOOL test image")


def main() -> None:
    with tempfile.TemporaryDirectory(prefix="greenpool-ocr-check-") as directory:
        image_path = Path(directory) / "synthetic.png"
        image = Image.new("RGB", (1200, 400), "white")
        draw = ImageDraw.Draw(image)
        font = ImageFont.load_default(size=64)
        draw.text((70, 130), "GREENPOOL OCR TEST 12345", fill="black", font=font)
        image.save(image_path)
        result = subprocess.run(
            [sys.executable, str(Path(__file__).with_name("paddle_ocr_worker.py")), str(image_path)],
            capture_output=True, text=True, timeout=600,
        )
        if result.returncode:
            sys.stderr.write(result.stderr)
            raise RuntimeError(f"OCR worker exited with code {result.returncode}")
        validate_payload(result.stdout)
    print("OCR_SMOKE_OK: imports, model initialization, prediction and JSON output passed")


if __name__ == "__main__":
    main()
