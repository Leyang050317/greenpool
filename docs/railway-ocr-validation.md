# Railway OCR validation

The web page's generic unavailable message is not an OCR diagnosis. The worker
must import its native libraries, load the models and perform a prediction.

## Build and runtime

- `railpack.json` extends (rather than replaces) Railpack's generated Apt lists.
- OpenCV needs `libgl1` and GLib (`libglib2.0-0`, which provides libgthread).
  Paddle's CPU runtime needs `libgomp1`. These are installed in both build and runtime.
- Python packages live under `/app/.venv-ocr`, not the builder's global Python.
- Linux model downloads live under `/app/ocr/.models` and are included with source
  build output. Do not mount a volume over `/app/ocr` or `/app/.venv-ocr`.
- The build runs `ocr/smoke_test.py` through the actual worker and requires recognition
  of a synthetic GREENPOOL image. It also records resolved packages in
  `ocr/runtime-packages.txt` in the image for diagnosis.
- Worker library startup output goes to stderr; stdout contains only the JSON contract.

## Before merging

Wait for **OCR Linux runtime check** on the repair branch/PR. It uses no application
secrets, database or personal documents. It verifies a real prediction twice in
separate Python processes. A second job builds the whole app with Railpack, then
runs the scan through Laravel inside the final image with networking disabled.
This checks deployment-layer contents and preloaded models, but does not certify
the production variables or authenticated web flow. CI uses Railpack 0.38.0;
check Railway's builder version when results differ.

If it fails, update this same branch before merging. Do not merge individual library
fixes into production to find the next missing dependency.

## Final Railway image check

In the running container, execute:

```sh
/app/.venv-ocr/bin/python /app/ocr/smoke_test.py
```

Expected: `OCR_SMOKE_OK`. This can also be appended to the existing Railway
pre-deploy commands if desired; retain any existing migrations/commands.
Do not enable public APP_DEBUG or publish request headers/identity documents.

Once the final image passes, test the authenticated licence and Geran upload flows.
Licence holder/profile matching, dates, document type, and plate consistency are
separate business validations and remain enabled. Vehicle-photo classification is
a separate Node model and is not certified by this OCR test.
