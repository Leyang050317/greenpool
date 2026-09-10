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

    public function test_front_photo_without_a_detectable_plate_is_rejected(): void
    {
        $lock = Mockery::mock(Lock::class);
        $lock->shouldReceive('get')->once()->andReturnTrue();
        $lock->shouldReceive('release')->once();
        Cache::shouldReceive('store->lock')->once()->andReturn($lock);

        $validator = Mockery::mock(VehicleImageValidationService::class);
        $validator->shouldReceive('validate')->once()->andReturn([
            'accepted' => true,
            'token' => 'token-value',
        ]);
        $validator->shouldNotReceive('attachPlateNumber');

        $plateExtractor = Mockery::mock(PlateNumberExtractionService::class);
        $plateExtractor->shouldReceive('extract')->once()->andReturnNull();

        $response = $this->controller($validator, $plateExtractor)->validateImage($this->request());

        $this->assertSame(422, $response->status());
        $this->assertSame('Inappropriate image. Please upload a clear front car image with a readable plate number.', $response->getData(true)['message']);
    }

    public function test_non_front_photo_without_a_detectable_plate_can_still_pass(): void
    {
        $lock = Mockery::mock(Lock::class);
        $lock->shouldReceive('get')->once()->andReturnTrue();
        $lock->shouldReceive('release')->once();
        Cache::shouldReceive('store->lock')->once()->andReturn($lock);

        $validator = Mockery::mock(VehicleImageValidationService::class);
        $validator->shouldReceive('validate')->once()->andReturn([
            'accepted' => true,
            'token' => 'token-value',
        ]);
        $validator->shouldReceive('attachPlateNumber')->once()->with('token-value', null)->andReturn('token-without-plate');

        $plateExtractor = Mockery::mock(PlateNumberExtractionService::class);
        $plateExtractor->shouldReceive('extract')->once()->andReturnNull();

        $response = $this->controller($validator, $plateExtractor)->validateImage($this->request('SIDE'));

        $this->assertSame(200, $response->status());
        $this->assertTrue($response->getData(true)['success']);
        $this->assertNull($response->getData(true)['plate_number']);
        $this->assertSame('token-without-plate', $response->getData(true)['token']);
    }

    private function controller(VehicleImageValidationService $validator, ?PlateNumberExtractionService $plateExtractor = null): VehicleController
    {
        return new VehicleController(
            Mockery::mock(DocumentOcrService::class), $validator,
            $plateExtractor ?? Mockery::mock(PlateNumberExtractionService::class),
        );
    }

    private function request(string $expectedView = 'FRONT'): Request
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('validate')->once()->andReturn([
            'image' => UploadedFile::fake()->create(strtolower($expectedView).'.jpg', 1, 'image/jpeg'),
            'expected_view' => $expectedView,
        ]);

        return $request;
    }
}
