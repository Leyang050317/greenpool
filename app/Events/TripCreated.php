<?php

namespace App\Events;

use App\Models\Trip;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TripCreated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public Trip $trip) {}

    public function broadcastOn(): Channel
    {
        return new Channel('trips');
    }

    public function broadcastWith(): array
    {
        return [
            'trip_id' => $this->trip->trip_id,
            'destination' => $this->trip->destination,
            'departure_at' => $this->trip->departure_at?->toIso8601String(),
            'available_seats' => $this->trip->available_seats,
            'created_at' => $this->trip->created_at?->toIso8601String(),
        ];
    }
}
