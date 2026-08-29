<?php

namespace App\Events;

use App\Models\Emergency;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmergencyAcknowledged implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public Emergency $emergency) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('trip.'.$this->emergency->trip_id);
    }

    public function broadcastWith(): array
    {
        return ['emergency_id' => $this->emergency->id, 'status' => $this->emergency->status, 'acknowledged_at' => $this->emergency->acknowledged_at?->toIso8601String()];
    }
}
