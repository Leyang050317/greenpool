<?php

return [
    'enabled' => (bool) env('LOCAL_VEHICLE_VISION_ENABLED', env('APP_ENV') !== 'testing'),
    'node_binary' => env('LOCAL_VEHICLE_VISION_NODE', 'node'),
    'worker' => base_path('vision/vehicle-image-classifier.mjs'),
    'model' => env('LOCAL_VEHICLE_VISION_MODEL', 'Xenova/clip-vit-base-patch32'),
    'cache_directory' => storage_path('app/models/transformers'),
    'timeout' => (int) env('LOCAL_VEHICLE_VISION_TIMEOUT', 180),
];
