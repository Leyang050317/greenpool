<?php

namespace App\Models;

use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    ];

    protected function casts(): array
    {
        return [
            'seat_capacity' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
