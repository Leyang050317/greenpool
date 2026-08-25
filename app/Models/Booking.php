<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'trip_id',
        'passenger_id',
        'booking_status',
        'number_of_seats',
        'number_of_luggage',
        'pickup_point',
        'pickup_place_id',
        'pickup_latitude',
        'pickup_longitude',
        'pickup_sequence',
        'picked_up_at',
    ];

    protected function casts(): array
    {
        return [
            'number_of_seats' => 'integer',
            'number_of_luggage' => 'integer',
            'picked_up_at' => 'datetime',
            'pickup_latitude' => 'decimal:7',
            'pickup_longitude' => 'decimal:7',
            'pickup_sequence' => 'integer',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class, 'trip_id', 'trip_id');
    }

    public function passenger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'passenger_id');
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }
}
