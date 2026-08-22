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

    public function __construct(
        public Booking $booking,
        public ?string $driverNotificationType = null,
        public ?string $passengerNotificationType = null,
    )
    {
        $this->booking->loadMissing(['trip']);
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('passenger.'.$this->booking->passenger_id),
            new PrivateChannel('driver.'.$this->booking->trip->user_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'booking_id' => $this->booking->id,
            'status' => $this->booking->booking_status,
            'trip_status' => $this->booking->trip->status,
            'driver_id' => $this->booking->trip->user_id,
            'trip_id' => $this->booking->trip_id,
            'driver_notification_type' => $this->driverNotificationType,
            'passenger_notification_type' => $this->passengerNotificationType,
            'updated_at' => $this->booking->updated_at?->toIso8601String(),
        ];
    }
}
