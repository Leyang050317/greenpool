<?php

namespace Tests\Unit;

use App\Services\Ocr\PaddleOcrService;
use App\Services\Vehicle\PlateNumberExtractionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class PlateOcrProfileTest extends TestCase
{
    public function test_plate_extraction_uses_mobile_profile_and_retains_plate_filtering(): void
    {
        Process::fake(['*' => Process::result(output: json_encode([
            'lines' => [
                ['text' => 'UNRELATED', 'confidence' => 1],
                ['text' => 'ABC 1234', 'confidence' => 0.99],
            ],
        ]))]);

        $plate = (new PlateNumberExtractionService(new PaddleOcrService))->extract(
            UploadedFile::fake()->create('front.jpg', 1, 'image/jpeg')
        );

        $this->assertSame('ABC1234', $plate);
        Process::assertRan(fn ($process) => array_slice($process->command, -2) === ['--profile', 'plate']);
    }
}
