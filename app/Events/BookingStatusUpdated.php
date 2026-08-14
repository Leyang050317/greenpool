<?php

namespace App\Events;

use App\Models\Booking;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public Booking $booking)
    {
        $this->booking->loadMissing(['trip']);
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('passenger.'.$this->booking->passenger_id);
    }

    public function broadcastWith(): array
    {
        return [
            'booking_id' => $this->booking->id,
            'status' => $this->booking->booking_status,
            'driver_id' => $this->booking->trip->user_id,
            'trip_id' => $this->booking->trip_id,
            'updated_at' => $this->booking->updated_at?->toIso8601String(),
        ];
    }
}
