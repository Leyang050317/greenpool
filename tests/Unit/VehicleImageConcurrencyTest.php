<?php

namespace Tests\Unit;

use App\Http\Controllers\Driver\VehicleController;
use App\Services\Ocr\DocumentOcrService;
use App\Services\Vehicle\PlateNumberExtractionService;
use App\Services\Vehicle\VehicleImageValidationService;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class VehicleImageConcurrencyTest extends TestCase
{
    public function test_busy_worker_rejects_another_model_load(): void
    {
        $lock = Mockery::mock(Lock::class);
        $lock->shouldReceive('get')->once()->andReturnFalse();
        Cache::shouldReceive('store->lock')->once()->andReturn($lock);
        $validator = Mockery::mock(VehicleImageValidationService::class);
        $validator->shouldNotReceive('validate');

        $this->assertSame(429, $this->controller($validator)->validateImage($this->request())->status());
    }

    public function test_worker_failure_releases_the_lock_for_retry(): void
    {
        $lock = Mockery::mock(Lock::class);
        $lock->shouldReceive('get')->once()->andReturnTrue();
        $lock->shouldReceive('release')->once();
        Cache::shouldReceive('store->lock')->once()->andReturn($lock);
        $validator = Mockery::mock(VehicleImageValidationService::class);
        $validator->shouldReceive('validate')->once()->andThrow(new \RuntimeException('Worker failed'));

        $this->assertSame(422, $this->controller($validator)->validateImage($this->request())->status());
    }

    private function controller(VehicleImageValidationService $validator): VehicleController
    {
        return new VehicleController(
            Mockery::mock(DocumentOcrService::class), $validator,
            Mockery::mock(PlateNumberExtractionService::class),
        );
    }

    private function request(): Request
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('validate')->once()->andReturn([
            'image' => UploadedFile::fake()->create('front.jpg', 1, 'image/jpeg'),
            'expected_view' => 'FRONT',
        ]);

        return $request;
    }
}
