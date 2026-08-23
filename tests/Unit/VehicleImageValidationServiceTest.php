<?php

namespace Tests\Unit;

use App\Services\Vehicle\VehicleImageValidationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class VehicleImageValidationServiceTest extends TestCase
{
    public function test_accepted_result_creates_a_file_and_view_bound_token(): void
    {
        config()->set('vehicle_vision.enabled', true);
        Process::fake([
            '*' => Process::result(output: json_encode([
                'is_vehicle' => true, 'detected_view' => 'FRONT', 'view_matches' => true,
                'quality' => 'ACCEPTABLE', 'requires_review' => false, 'accepted' => true,
                'message' => 'Front vehicle image accepted.',
            ])),
        ]);
        $file = UploadedFile::fake()->image('front.jpg', 800, 450);
        $service = new VehicleImageValidationService;

        $result = $service->validate($file, 'FRONT');

        $this->assertTrue($result['accepted']);
        $this->assertTrue($service->tokenMatches($result['token'], $file, 'FRONT'));
        $this->assertFalse($service->tokenMatches($result['token'], $file, 'REAR'));
        Process::assertRan(fn ($process) => in_array('FRONT', $process->command, true));
    }
}
