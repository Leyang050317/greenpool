<?php

namespace App\Services\Ocr;

use Illuminate\Http\UploadedFile;

class DocumentOcrService
{
    public function __construct(
        private readonly PaddleOcrService $ocr,
        private readonly DocumentTypeDetector $detector,
    ) {}

    public function process(UploadedFile $file, ?string $expectedType = null): array
    {
        $raw = $this->ocr->scan($file);
        $type = $this->detector->detect($raw['full_text'] ?? '');
        if ($type === null) {
            throw new OcrException('Unable to identify the document type. Please upload a Malaysian Driving Licence or Vehicle Ownership Certificate.');
        }
        if ($expectedType !== null && $type !== $expectedType) {
            throw new OcrException('The uploaded image does not match the selected document type.');
        }

        $threshold = (float) config('ocr.low_confidence_threshold');
        $parser = $type === DocumentTypeDetector::DRIVING_LICENCE
            ? new DrivingLicenceParser($threshold)
            : new VehicleGeranParser($threshold);
        $fields = $parser->parse($raw['lines']);

        return [
            'document_type' => $type,
            'requires_review' => collect($fields)->contains(fn (array $field) => $field['requires_review']),
            'fields' => $fields,
        ];
    }
}
