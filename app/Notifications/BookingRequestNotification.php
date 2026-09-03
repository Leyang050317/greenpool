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

        $isCancelled = in_array($this->action, ['cancelled', 'cancelled_confirmed', 'not_boarding'], true);
        $passengerName = $this->booking->passenger->name;
        $route = $this->booking->trip->departure_location.' → '.$this->booking->trip->destination;

        return [
            'booking_id' => $this->booking->id,
            'trip_id' => $this->booking->trip_id,
            'passenger_id' => $this->booking->passenger_id,
            'passenger_name' => $passengerName,
            'pickup_point' => $this->booking->pickup_point,
            'booking_status' => $this->booking->booking_status,
            'type' => 'booking_request_'.$this->action,
            'title' => match ($this->action) {
                'cancelled_confirmed' => 'Confirmed booking cancelled',
                'not_boarding' => 'Passenger will not board',
                'cancelled' => 'Booking request cancelled',
                default => 'New booking request',
            },
            'icon' => $isCancelled ? 'circle-x' : 'clipboard-list',
            'message' => match ($this->action) {
                'cancelled_confirmed' => "{$passengerName} cancelled their confirmed booking for {$route}. The seats are available again.",
                'not_boarding' => "{$passengerName} will not board {$route}. Please skip their pickup point.",
                'cancelled' => "{$passengerName} cancelled their booking request for {$route}.",
                default => "{$passengerName} requested a seat on your trip from {$route}.",
            },
            'url' => route('driver.booking-requests.show', $this->booking),
        ];
    }
}
