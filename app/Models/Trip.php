<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Trip extends Model
{
    use HasFactory, SoftDeletes;

    protected $primaryKey = 'trip_id';

    protected $fillable = [
        'vehicle_id', 'departure_location', 'destination', 'departure_at',
        'available_seats', 'price_per_passenger', 'description', 'estimated_distance_km', 'estimated_duration_seconds', 'status',
        'departure_latitude', 'departure_longitude', 'destination_latitude', 'destination_longitude',
        'started_at', 'completed_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'departure_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'available_seats' => 'integer',
            'price_per_passenger' => 'decimal:2',
            'estimated_distance_km' => 'decimal:2',
            'estimated_duration_seconds' => 'integer',
            'departure_latitude' => 'decimal:7',
            'departure_longitude' => 'decimal:7',
            'destination_latitude' => 'decimal:7',
            'destination_longitude' => 'decimal:7',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id', 'vehicle_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'trip_id', 'trip_id');
    }

    public function getDurationAttribute(): ?string
    {
        if (! $this->started_at || ! $this->completed_at) {
            return null;
        }

        $minutes = $this->started_at->diffInMinutes($this->completed_at);
        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        return $hours > 0 ? "{$hours}h {$remainingMinutes}m" : "{$remainingMinutes}m";
    }
}
