<?php

namespace App\Models;

use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    /** @use HasFactory<VehicleFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $primaryKey = 'vehicle_id';

    protected $fillable = [
        'user_id',
        'plate_number',
        'brand',
        'model',
        'colour',
        'seat_capacity',
        'status',
        'vehicle_image_path',
        'verification_status',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'seat_capacity' => 'integer',
            'verified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class, 'vehicle_id', 'vehicle_id');
    }

    public function scopeVerified($query)
    {
        return $query->where('verification_status', 'Verified');
    }

    public function scopeSelectableForTrips($query)
    {
        return $query->where('status', 'Active')->verified();
    }
}
