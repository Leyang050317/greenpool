<?php

namespace App\Models\Concerns;

trait HasProfileCompleteness
{
    public function profileCompleteness(): array
    {
        $percentage = 0;

        if (filled($this->name) && filled($this->email)) {
            $percentage += 40;
        }

        if (filled($this->photo)) {
            $percentage += 20;
        }

        if ($this->phone_verified_at !== null) {
            $percentage += 20;
        }

        if (filled($this->google_id) || $this->hasEmergencyContact()) {
            $percentage += 20;
        }

        return [
            'percentage' => $percentage,
            'next_action_hint' => $this->nextProfileCompletenessHint($percentage),
        ];
    }

    private function hasEmergencyContact(): bool
    {
        if ($this->relationLoaded('emergencyContacts')) {
            return $this->emergencyContacts->isNotEmpty();
        }

        return $this->emergencyContacts()->exists();
    }

    private function nextProfileCompletenessHint(int $percentage): ?string
    {
        if ($percentage === 100) {
            return null;
        }

        if (blank($this->photo)) {
            return 'Upload a profile photo to reach '.min($percentage + 20, 100).'%.';
        }

        if (blank($this->phone_number)) {
            return 'Add your phone number to reach '.min($percentage + 20, 100).'%.';
        }

        if ($this->phone_verified_at === null) {
            return 'Verify your phone number to reach '.min($percentage + 20, 100).'%.';
        }

        if (blank($this->google_id) && ! $this->hasEmergencyContact()) {
            return 'Connect Google or add an emergency contact to reach '.min($percentage + 20, 100).'%.';
        }

        return 'Complete your remaining profile details to improve your account security.';
    }
}
