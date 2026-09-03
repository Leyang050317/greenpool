<?php

namespace Tests\Unit;

use App\Services\Ocr\PaddleOcrService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class OcrExecutionBudgetTest extends TestCase
{
    public function test_ocr_resets_php_budget_after_vehicle_classification(): void
    {
        $previousLimit = (int) ini_get('max_execution_time');
        config()->set('ocr.timeout', 300);
        Process::fake(['*' => Process::result(output: json_encode([
            'lines' => [['text' => 'TEST123', 'confidence' => 0.99]],
        ]))]);

        try {
            set_time_limit(30);
            $result = (new PaddleOcrService)->scan(
                UploadedFile::fake()->create('plate.jpg', 1, 'image/jpeg')
            );
            $this->assertSame(330, (int) ini_get('max_execution_time'));
            $this->assertSame('TEST123', $result['lines'][0]['text']);
            Process::assertRan(fn ($process) => $process->timeout === 300);
        } finally {
            set_time_limit($previousLimit);
        }
    }
}
