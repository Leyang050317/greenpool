<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TripUpdatedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Booking $booking) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $this->booking->loadMissing('trip');
        $trip = $this->booking->trip;

        return [
            'booking_id' => $this->booking->id,
            'trip_id' => $trip->trip_id,
            'type' => 'trip_updated',
            'title' => 'Your trip was updated',
            'icon' => 'route',
            'message' => 'Your driver updated the trip from '.$trip->departure_location.' to '.$trip->destination.'.',
            'url' => route('passenger.bookings.show', $this->booking),
        ];
    }
}
