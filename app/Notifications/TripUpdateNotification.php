<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Models\Trip;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TripUpdateNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Trip $trip,
        private readonly string $message,
        private readonly ?Booking $booking = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'trip_id' => $this->trip->trip_id,
            'booking_id' => $this->booking?->id,
            'type' => 'trip_update',
            'title' => 'Trip Update',
            'icon' => 'route',
            'message' => $this->message,
            'url' => $this->booking
                ? route('passenger.bookings.show', $this->booking)
                : route('driver.trips.journey'),
        ];
    }
}
