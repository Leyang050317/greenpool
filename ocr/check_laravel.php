<?php

// CLI-only smoke-test bridge. No route, database changes or personal documents.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (! isset($argv[1]) || ! is_file($argv[1])) {
    throw new \InvalidArgumentException('Synthetic test image is required.');
}

$file = new \Illuminate\Http\UploadedFile($argv[1], 'synthetic.png', 'image/png', null, true);
$result = $app->make(\App\Services\Ocr\PaddleOcrService::class)->scan($file);
echo json_encode($result, JSON_THROW_ON_ERROR);
