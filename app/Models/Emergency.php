<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Emergency extends Model
{
    use HasFactory;

    protected $fillable = [
        'trip_id', 'user_id', 'role', 'issue_type', 'latitude', 'longitude', 'location_source', 'description', 'status',
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
            'safety_risk' => 'Immediate Safety Risk',
            'accident_road_danger' => 'Accident / Road Danger',
            'other_emergency' => 'Other Emergency',
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
            'medical_emergency' => 'A trip participant reported a medical emergency.',
            'safety_risk' => 'A trip participant reported an immediate safety risk.',
            'accident_road_danger' => 'A trip participant reported an accident or road danger.',
            'other_emergency' => 'A trip participant reported an emergency.',
            default => $this->role === 'driver' ? 'Driver reported an issue.' : 'Passenger reported an issue.',
        };
    }

    public function locationStatusLabel(): string
    {
        return match ($this->location_source) {
            'device' => 'Current device location',
            'pickup' => 'Pickup location fallback',
            'departure' => 'Departure location fallback',
            'unavailable' => 'Location unavailable',
            default => 'Location source not confirmed',
        };
    }

    public function mapUrl(): ?string
    {
        if ($this->latitude === null || $this->longitude === null) {
            return null;
        }

        return 'https://www.google.com/maps?q='.$this->latitude.','.$this->longitude;
    }
}
