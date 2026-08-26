<?php

namespace Tests\Unit;

use App\Services\Ocr\PaddleOcrService;
use App\Services\Vehicle\PlateNumberExtractionService;
use Illuminate\Http\UploadedFile;
use Mockery\MockInterface;
use Tests\TestCase;

class PlateNumberExtractionServiceTest extends TestCase
{
    public function test_it_extracts_and_normalizes_a_malaysian_plate_number(): void
    {
        $this->mock(PaddleOcrService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('scan')->once()->andReturn(['lines' => [
                ['text' => 'TOYOTA', 'confidence' => 0.99],
                ['text' => 'WA 3171 R', 'confidence' => 0.94],
            ]]);
        });

        $plate = app(PlateNumberExtractionService::class)
            ->extract(UploadedFile::fake()->image('front.jpg', 800, 450));

        $this->assertSame('WA3171R', $plate);
    }

    public function test_it_returns_null_when_no_plate_pattern_is_read(): void
    {
        $this->mock(PaddleOcrService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('scan')->once()->andReturn(['lines' => [
                ['text' => 'TOYOTA VIOS', 'confidence' => 0.98],
                ['text' => 'FRONT VIEW', 'confidence' => 0.91],
            ]]);
        });

        $plate = app(PlateNumberExtractionService::class)
            ->extract(UploadedFile::fake()->image('front.jpg', 800, 450));

        $this->assertNull($plate);
    }
}
