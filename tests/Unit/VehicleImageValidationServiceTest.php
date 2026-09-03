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
                'detected_colour' => 'RED', 'colour_group' => 'RED', 'colour_reliable' => true,
                'message' => 'Front vehicle image accepted.',
            ])),
        ]);
        // The child process is faked; this test only needs bytes for the bound token.
        $file = UploadedFile::fake()->create('front.jpg', 1, 'image/jpeg');
        $service = new VehicleImageValidationService;

        $result = $service->validate($file, 'FRONT');

        $this->assertTrue($result['accepted']);
        $this->assertTrue($service->tokenMatches($result['token'], $file, 'FRONT'));
        $this->assertSame('RED', $service->tokenPayload($result['token'], $file, 'FRONT')['colour']);
        $this->assertFalse($service->tokenMatches($result['token'], $file, 'REAR'));
        Process::assertRan(fn ($process) => in_array('FRONT', $process->command, true));
    }

    public function test_php_budget_exceeds_the_configured_worker_timeout(): void
    {
        $previousLimit = (int) ini_get('max_execution_time');
        config()->set('vehicle_vision.enabled', true);
        config()->set('vehicle_vision.timeout', 180);
        Process::fake(['*' => Process::result(output: json_encode([
            'view_matches' => false, 'is_vehicle' => false,
        ]))]);

        try {
            $result = (new VehicleImageValidationService)->validate(
                UploadedFile::fake()->create('front.jpg', 1, 'image/jpeg'), 'FRONT'
            );
            $this->assertSame(210, (int) ini_get('max_execution_time'));
            $this->assertFalse($result['accepted']);
            $this->assertNull($result['token']);
            Process::assertRan(fn ($process) => $process->timeout === 180);
        } finally {
            set_time_limit($previousLimit);
        }
    }
}
