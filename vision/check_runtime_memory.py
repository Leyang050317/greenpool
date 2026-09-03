"""Measure real vehicle/plate workers on repository example photos, never user uploads."""
import json
from pathlib import Path
import resource
import subprocess
import sys

root = Path(__file__).resolve().parents[1]
for view in ("front", "rear", "side"):
    photo = root / "public/images/vehicle-photo-examples" / f"{view}-example.png"
    for stage, command in (
        ("vehicle", ["node", str(root / "vision/vehicle-image-classifier.mjs"),
                     str(photo), view.upper(), "Xenova/clip-vit-base-patch32",
                     str(root / "storage/app/models/transformers")]),
        ("plate-ocr", [sys.executable, str(root / "ocr/paddle_ocr_worker.py"), str(photo)]),
    ):
        print(f"BEGIN {view} {stage}", flush=True)
        result = subprocess.run(command, capture_output=True, text=True, timeout=360)
        peak = resource.getrusage(resource.RUSAGE_CHILDREN).ru_maxrss / 1024
        print(f"END {view} {stage}: exit={result.returncode} child_peak_MiB={peak:.1f}", flush=True)
        if result.returncode:
            print(result.stderr[-4000:], flush=True)
            raise SystemExit(1)
        payload = json.loads(result.stdout)
        if stage == "vehicle":
            print(json.dumps({key: payload.get(key) for key in ("accepted", "reason_code")}), flush=True)
        else:
            print(f"OCR lines: {len(payload.get('lines', []))}", flush=True)
print("VEHICLE_MEMORY_CHECK_OK", flush=True)
