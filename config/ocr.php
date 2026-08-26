<?php

return [
    'python_binary' => env('OCR_PYTHON_BINARY', 'python'),
    'worker_path' => base_path('ocr/paddle_ocr_worker.py'),
    'timeout' => (int) env('OCR_TIMEOUT', 120),
    'low_confidence_threshold' => (float) env('OCR_LOW_CONFIDENCE_THRESHOLD', 0.75),
];
