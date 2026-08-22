<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BookingRequestNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Booking $booking,
        private readonly string $action,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $this->booking->loadMissing(['passenger', 'trip']);

        $isCancelled = $this->action === 'cancelled';
        $passengerName = $this->booking->passenger->name;
        $destination = $this->booking->trip->destination;

        return [
            'booking_id' => $this->booking->id,
            'trip_id' => $this->booking->trip_id,
            'passenger_id' => $this->booking->passenger_id,
            'passenger_name' => $passengerName,
            'pickup_point' => $this->booking->pickup_point,
            'booking_status' => $this->booking->booking_status,
            'type' => 'booking_request_'.$this->action,
            'title' => $isCancelled ? 'Booking request cancelled' : 'New booking request',
            'icon' => $isCancelled ? 'circle-x' : 'clipboard-list',
            'message' => $isCancelled
                ? "{$passengerName} cancelled their booking request to {$destination}."
                : "{$passengerName} submitted a booking request to {$destination}.",
            'url' => route('driver.booking-requests.show', $this->booking),
        ];
    }
}
