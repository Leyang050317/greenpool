<?php

namespace App\Events;

use App\Models\Booking;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingCreated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public Booking $booking)
    {
        $this->booking->loadMissing(['passenger', 'trip']);
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('driver.'.$this->booking->trip->user_id);
    }

    public function broadcastWith(): array
    {
        return [
            'booking_id' => $this->booking->id,
            'passenger_name' => $this->booking->passenger->name,
            'pickup_point' => $this->booking->pickup_point,
            'number_of_seats' => $this->booking->number_of_seats,
            'status' => $this->booking->booking_status,
            'trip_id' => $this->booking->trip_id,
            'trip_route' => $this->booking->trip->departure_location.' → '.$this->booking->trip->destination,
            'url' => route('driver.booking-requests.show', $this->booking),
            'created_at' => $this->booking->created_at?->toIso8601String(),
        ];
    }
}
