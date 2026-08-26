<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverLicence extends Model
{
    protected $fillable = [
        'image_path', 'holder_name', 'identity_no', 'licence_class',
        'valid_from', 'valid_until', 'verification_status', 'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'valid_from' => 'date',
            'valid_until' => 'date',
            'verified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isValidOn(CarbonInterface $date): bool
    {
        return $this->verification_status === 'Verified'
            && ! $this->valid_until->copy()->startOfDay()->lt($date->copy()->startOfDay());
    }
}
