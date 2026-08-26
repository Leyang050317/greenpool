<?php

namespace App\Events;

use App\Models\TripLocation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TripLocationUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public TripLocation $location) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('trip.'.$this->location->trip_id);
    }

    public function broadcastWith(): array
    {
        return [
            'trip_id' => $this->location->trip_id,
            'latitude' => (float) $this->location->latitude,
            'longitude' => (float) $this->location->longitude,
            'accuracy_meters' => $this->location->accuracy_meters === null ? null : (float) $this->location->accuracy_meters,
            'heading' => $this->location->heading === null ? null : (float) $this->location->heading,
            'recorded_at' => $this->location->recorded_at->toIso8601String(),
        ];
    }
}
