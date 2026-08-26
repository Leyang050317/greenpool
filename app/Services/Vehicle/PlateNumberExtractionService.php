<?php

namespace App\Services\Vehicle;

use App\Services\Ocr\PaddleOcrService;
use Illuminate\Http\UploadedFile;

class PlateNumberExtractionService
{
    public function __construct(private readonly PaddleOcrService $ocr) {}

    public function extract(UploadedFile $image): ?string
    {
        $lines = $this->ocr->scan($image)['lines'] ?? [];

        return collect($lines)
            ->map(function (array $line): ?array {
                $compact = mb_strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string) ($line['text'] ?? '')) ?? '');
                if (! preg_match('/^[A-Z]{1,3}[0-9]{1,4}[A-Z]?$/', $compact)) {
                    return null;
                }

                return [
                    'plate' => $compact,
                    'confidence' => (float) ($line['confidence'] ?? 0),
                ];
            })
            ->filter()
            ->sortByDesc('confidence')
            ->value('plate');
    }
}
