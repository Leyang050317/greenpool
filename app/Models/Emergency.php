<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Emergency extends Model
{
    use HasFactory;

    protected $fillable = [
        'trip_id', 'user_id', 'role', 'issue_type', 'latitude', 'longitude', 'description', 'status',
        'triggered_at', 'acknowledged_at', 'acknowledged_by', 'resolved_at', 'resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7', 'longitude' => 'decimal:7',
            'triggered_at' => 'datetime', 'acknowledged_at' => 'datetime', 'resolved_at' => 'datetime',
        ];
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class, 'trip_id', 'trip_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function issueLabel(): string
    {
        return [
            'traffic_delay' => 'Traffic Delay',
            'vehicle_problem' => 'Vehicle Problem',
            'road_hazard' => 'Road Hazard',
            'personal_emergency' => 'Personal Emergency',
            'medical_emergency' => 'Medical Emergency',
            'other' => 'Other',
        ][$this->issue_type] ?? 'Issue';
    }

    public function notificationMessage(): string
    {
        return match ($this->issue_type) {
            'traffic_delay' => 'Driver reported a traffic delay. Your trip may be delayed.',
            'vehicle_problem' => 'Driver reported a vehicle problem for your trip.',
            'road_hazard' => 'Driver reported a road hazard.',
            'personal_emergency' => 'Passenger reported a personal emergency.',
            'medical_emergency' => 'Passenger reported a medical emergency during the trip.',
            default => $this->role === 'driver' ? 'Driver reported an issue.' : 'Passenger reported an issue.',
        };
    }
}
