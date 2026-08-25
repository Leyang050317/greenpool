<?php

namespace App\Events;

use App\Models\Booking;
use App\Models\Trip;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TripReminderSent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public Trip $trip,
        public int $userId,
        public string $role,
        public ?Booking $booking = null,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel($this->role.'.'.$this->userId);
    }

    public function broadcastWith(): array
    {
        $route = $this->trip->departure_location.' → '.$this->trip->destination;

        return [
            'trip_id' => $this->trip->trip_id,
            'booking_id' => $this->booking?->id,
            'title' => 'Trip Reminder',
            'message' => "Your trip from {$route} starts in approximately 30 minutes.",
            'url' => $this->booking
                ? route('passenger.bookings.show', $this->booking)
                : route('driver.trips.show', $this->trip),
        ];
    }
}
