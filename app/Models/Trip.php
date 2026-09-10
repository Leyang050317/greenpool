<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Trip extends Model
{
    use HasFactory, SoftDeletes;

    protected $primaryKey = 'trip_id';

    protected $attributes = [
        'version' => 1,
    ];

    protected $fillable = [
        'vehicle_id', 'departure_location', 'destination', 'departure_at',
        'available_seats', 'price_per_passenger', 'description', 'estimated_distance_km', 'estimated_duration_seconds', 'estimated_arrival_at', 'status', 'version',
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
            'version' => 'integer',
            'estimated_arrival_at' => 'datetime',
            'departure_latitude' => 'decimal:7',
            'departure_longitude' => 'decimal:7',
            'destination_latitude' => 'decimal:7',
            'destination_longitude' => 'decimal:7',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (Trip $trip): void {
            if (! $trip->isDirty('version')) {
                $trip->version = ((int) $trip->version) + 1;
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id', 'vehicle_id')->withTrashed();
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'trip_id', 'trip_id');
    }

    public function emergencies(): HasMany
    {
        return $this->hasMany(Emergency::class, 'trip_id', 'trip_id');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(TripLocation::class, 'trip_id', 'trip_id');
    }

    public function latestLocation(): HasOne
    {
        return $this->hasOne(TripLocation::class, 'trip_id', 'trip_id')->latestOfMany('recorded_at');
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

    /**
     * Departure expiry is a minute-level rule: the scheduled minute remains valid.
     */
    public function hasExpiredDeparture(?Carbon $currentTime = null): bool
    {
        return ($currentTime ?? now())->copy()->startOfMinute()
            ->gt($this->departure_at->copy()->startOfMinute());
    }

    public function earliestStartAt(): Carbon
    {
        return $this->departure_at->copy()->subMinutes((int) config('trips.start_early_minutes', 30));
    }

    public function isTooEarlyToStart(?Carbon $currentTime = null): bool
    {
        return ($currentTime ?? now())->lt($this->earliestStartAt());
    }
}
