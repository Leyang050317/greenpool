<?php

namespace App\Events;

use App\Models\Booking;
use App\Models\Emergency;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmergencyTriggered implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public Emergency $emergency, public int $userId, public string $role, public ?Booking $booking = null) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel($this->role.'.'.$this->userId);
    }

    public function broadcastWith(): array
    {
        $trip = $this->emergency->trip;
        return [
            'emergency_id' => $this->emergency->id,
            'trip_id' => $trip->trip_id,
            'booking_id' => $this->booking?->id,
            'title' => 'Issue Report',
            'message' => $this->emergency->notificationMessage(),
            'url' => $this->booking ? route('passenger.bookings.show', $this->booking) : route('driver.trips.show', $trip),
        ];
    }
}
