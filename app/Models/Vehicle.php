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
        'front_image_path', 'rear_image_path', 'side_image_path',
        'vehicle_geran_path', 'driving_licence_path',
        'voc_reference_no', 'registered_owner_name', 'owner_identity_no', 'owner_address',
        'chassis_no', 'engine_no', 'manufacturer', 'model_name', 'engine_capacity', 'fuel_type',
        'origin_status', 'usage_class', 'body_type', 'manufacturing_year', 'registration_date',
        'licence_name', 'licence_identity_no', 'date_of_birth', 'nationality', 'licence_class',
        'licence_valid_from', 'licence_valid_until', 'licence_address',
        'verification_status',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'seat_capacity' => 'integer',
            'verified_at' => 'datetime',
            'date_of_birth' => 'date',
            'registration_date' => 'date',
            'licence_valid_from' => 'date',
            'licence_valid_until' => 'date',
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
