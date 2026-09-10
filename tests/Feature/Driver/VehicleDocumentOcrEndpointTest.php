<?php

namespace Tests\Feature\Driver;

use App\Models\User;
use App\Services\Ocr\DocumentOcrService;
use App\Services\Ocr\OcrException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Mockery\MockInterface;
use Tests\TestCase;

class VehicleDocumentOcrEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_can_scan_a_document_and_receives_reviewable_fields(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $this->mock(DocumentOcrService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('process')->once()->andReturn([
                'document_type' => 'DRIVING_LICENCE',
                'requires_review' => true,
                'fields' => ['identity_no' => ['value' => '991109O40290', 'confidence' => 0.61, 'requires_review' => true]],
            ]);
        });

        $this->actingAs($driver)->postJson(route('driver.vehicles.documents.ocr'), [
            'document' => UploadedFile::fake()->image('licence.jpg', 800, 500),
            'expected_document_type' => 'DRIVING_LICENCE',
        ])->assertOk()
            ->assertJsonPath('requires_review', true)
            ->assertJsonPath('fields.identity_no.value', '991109O40290');
    }

    public function test_ocr_endpoint_rejects_non_images_and_non_drivers(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $passenger = User::factory()->create(['role' => 'passenger']);
        $payload = [
            'document' => UploadedFile::fake()->create('document.pdf', 10, 'application/pdf'),
            'expected_document_type' => 'DRIVING_LICENCE',
        ];

        $this->actingAs($driver)->postJson(route('driver.vehicles.documents.ocr'), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('document');
        $this->actingAs($passenger)->postJson(route('driver.vehicles.documents.ocr'), $payload)
            ->assertForbidden();
    }

    public function test_vehicle_geran_scan_without_readable_text_uses_inappropriate_image_message(): void
    {
        $driver = User::factory()->create(['role' => 'driver']);
        $this->mock(DocumentOcrService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('process')->once()
                ->andThrow(new OcrException('No readable text was detected. Please upload a clearer document image.'));
        });

        $this->actingAs($driver)->postJson(route('driver.vehicles.documents.ocr'), [
            'document' => UploadedFile::fake()->image('parking.jpg', 800, 500),
            'expected_document_type' => 'VEHICLE_GERAN',
        ])->assertUnprocessable()
            ->assertJsonPath('message', DocumentOcrService::INAPPROPRIATE_VEHICLE_GERAN_MESSAGE);
    }
}
