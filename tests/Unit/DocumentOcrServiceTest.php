<?php

namespace Tests\Unit;

use App\Services\Ocr\DocumentOcrService;
use App\Services\Ocr\DocumentTypeDetector;
use App\Services\Ocr\OcrException;
use App\Services\Ocr\PaddleOcrService;
use Illuminate\Http\UploadedFile;
use Mockery;
use Tests\TestCase;

class DocumentOcrServiceTest extends TestCase
{
    public function test_unidentified_vehicle_geran_scan_uses_inappropriate_image_message(): void
    {
        $ocr = Mockery::mock(PaddleOcrService::class);
        $ocr->shouldReceive('scan')->once()->andReturn([
            'full_text' => 'A5 B1 PARKING LEVEL',
            'lines' => $this->lines(['A5 B1 PARKING LEVEL']),
        ]);

        $this->expectException(OcrException::class);
        $this->expectExceptionMessage(DocumentOcrService::INAPPROPRIATE_VEHICLE_GERAN_MESSAGE);

        (new DocumentOcrService($ocr, new DocumentTypeDetector))->process(
            UploadedFile::fake()->image('parking.jpg', 800, 500),
            DocumentTypeDetector::VEHICLE_GERAN,
        );
    }

    public function test_vehicle_geran_scan_missing_required_details_uses_inappropriate_image_message(): void
    {
        $ocr = Mockery::mock(PaddleOcrService::class);
        $ocr->shouldReceive('scan')->once()->andReturn([
            'full_text' => "SIJIL PEMILIKAN KENDERAAN\nNO. PENDAFTARAN\nNO. CHASIS",
            'lines' => $this->lines([
                'SIJIL PEMILIKAN KENDERAAN',
                'NO. PENDAFTARAN: VAB 1234',
                'NO. CHASIS: PL1BT3SRRSB407045',
            ]),
        ]);

        $this->expectException(OcrException::class);
        $this->expectExceptionMessage(DocumentOcrService::INAPPROPRIATE_VEHICLE_GERAN_MESSAGE);

        (new DocumentOcrService($ocr, new DocumentTypeDetector))->process(
            UploadedFile::fake()->image('geran.jpg', 800, 500),
            DocumentTypeDetector::VEHICLE_GERAN,
        );
    }

    private function lines(array $values): array
    {
        return array_map(fn (string $text) => [
            'text' => $text,
            'confidence' => 0.98,
            'bounding_box' => [],
        ], $values);
    }
}
