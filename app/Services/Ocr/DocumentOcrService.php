<?php

namespace App\Services\Ocr;

use Illuminate\Http\UploadedFile;

class DocumentOcrService
{
    public const INAPPROPRIATE_VEHICLE_GERAN_MESSAGE = 'Inappropriate image. Please upload a clear Vehicle Geran / VOC image with the required details visible.';

    public function __construct(
        private readonly PaddleOcrService $ocr,
        private readonly DocumentTypeDetector $detector,
    ) {}

    public function process(UploadedFile $file, ?string $expectedType = null): array
    {
        $raw = $this->ocr->scan($file);
        $type = $this->detector->detect($raw['full_text'] ?? '');
        if ($type === null) {
            throw new OcrException($expectedType === DocumentTypeDetector::VEHICLE_GERAN
                ? self::INAPPROPRIATE_VEHICLE_GERAN_MESSAGE
                : 'Unable to identify the document type. Please upload a Malaysian Driving Licence or Vehicle Ownership Certificate.');
        }
        if ($expectedType !== null && $type !== $expectedType) {
            throw new OcrException($expectedType === DocumentTypeDetector::VEHICLE_GERAN
                ? self::INAPPROPRIATE_VEHICLE_GERAN_MESSAGE
                : 'The uploaded image does not match the selected document type.');
        }

        $threshold = (float) config('ocr.low_confidence_threshold');
        $parser = $type === DocumentTypeDetector::DRIVING_LICENCE
            ? new DrivingLicenceParser($threshold)
            : new VehicleGeranParser($threshold);
        $fields = $parser->parse($raw['lines']);
        if ($type === DocumentTypeDetector::VEHICLE_GERAN && $this->missingRequiredGeranFields($fields) !== []) {
            throw new OcrException(self::INAPPROPRIATE_VEHICLE_GERAN_MESSAGE);
        }

        return [
            'document_type' => $type,
            'requires_review' => collect($fields)->contains(fn (array $field) => $field['requires_review']),
            'fields' => $fields,
        ];
    }

    private function missingRequiredGeranFields(array $fields): array
    {
        return array_values(array_filter([
            'registration_no',
            'registered_owner_name',
            'owner_identity_no',
            'manufacturer',
            'model_name',
        ], fn (string $field) => blank($fields[$field]['value'] ?? null)));
    }
}
