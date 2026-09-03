<?php

namespace Tests\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;

trait CreatesVehicleValidationTokens
{
    /**
     * Build the same short-lived, file-bound payload that the photo validation
     * endpoint supplies to the browser. The classifier itself is covered by
     * its service test; feature tests should not launch Node or download models.
     */
    protected function vehicleValidationToken(UploadedFile $file, string $view, ?string $plateNumber = null, string $colour = 'SILVER'): string
    {
        return Crypt::encryptString(json_encode([
            'hash' => hash_file('sha256', $file->getRealPath()),
            'view' => $view,
            'colour' => $colour,
            'detected_colour' => $colour,
            'colour_group' => $colour,
            'colour_confidence' => 0.99,
            'plate_number' => $plateNumber,
            'expires_at' => now()->addMinutes(30)->timestamp,
        ], JSON_THROW_ON_ERROR));
    }

    /** @param array<string, mixed> $data */
    protected function withVehicleValidationTokens(array $data): array
    {
        $colour = mb_strtoupper(trim((string) ($data['colour'] ?? 'SILVER')));
        $plateNumber = (string) ($data['plate_number'] ?? '');

        foreach (['front_image' => 'FRONT', 'rear_image' => 'REAR', 'side_image' => 'SIDE'] as $field => $view) {
            if (($data[$field] ?? null) instanceof UploadedFile) {
                $data[$field.'_validation_token'] = $this->vehicleValidationToken($data[$field], $view, $plateNumber, $colour);
            }
        }

        return $data;
    }
}
