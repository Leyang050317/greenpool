<?php

namespace App\Events;

use App\Models\Emergency;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmergencyResolved implements ShouldBroadcastNow
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
        return [
            'emergency_id' => $this->emergency->id,
            'status' => $this->emergency->status,
            'resolved_at' => $this->emergency->resolved_at?->toIso8601String(),
            'resolved_by' => $this->emergency->resolvedBy?->name,
        ];
    }
}
