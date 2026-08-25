<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Models\Trip;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TripReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Trip $trip,
        private readonly ?Booking $booking = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $route = $this->trip->departure_location.' → '.$this->trip->destination;
        $isPassenger = $this->booking !== null;

        return [
            'booking_id' => $this->booking?->id,
            'trip_id' => $this->trip->trip_id,
            'type' => 'trip_reminder',
            'title' => 'Trip Reminder',
            'icon' => 'clock',
            'message' => $isPassenger
                ? "Your trip from {$route} starts in approximately 30 minutes."
                : "Your trip from {$route} starts in approximately 30 minutes.",
            'url' => $isPassenger
                ? route('passenger.bookings.show', $this->booking)
                : route('driver.trips.show', $this->trip),
        ];
    }
}
