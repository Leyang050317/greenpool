<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BookingStatusNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Booking $booking,
        private readonly string $status,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $this->booking->loadMissing(['trip.user']);

        $driverName = $this->booking->trip->user->name;
        $route = $this->booking->trip->departure_location.' → '.$this->booking->trip->destination;
        $statusKey = strtolower($this->status);

        $title = match ($this->status) {
            'Accepted' => 'Booking Accepted',
            'Rejected' => 'Booking Rejected',
            'Started' => 'Trip Started',
            'Completed' => 'Trip Completed',
            'Cancelled' => 'Trip Cancelled',
            default => 'Booking updated',
        };

        $icon = match ($this->status) {
            'Accepted' => 'circle-check',
            'Rejected', 'Cancelled' => 'circle-x',
            'Started' => 'play',
            'Completed' => 'circle-check',
            default => 'bell',
        };

        $message = match ($this->status) {
            'Accepted' => "Your booking for {$route} has been accepted.",
            'Rejected' => "Your booking request for {$route} was rejected.",
            'Started' => "Your trip from {$route} has started.",
            'Completed' => "Your trip from {$route} has been completed. You can now review your payment and rating information.",
            'Cancelled' => "Your booked trip from {$route} has been cancelled.",
            default => "Your booking for {$route} was updated.",
        };

        return [
            'booking_id' => $this->booking->id,
            'trip_id' => $this->booking->trip_id,
            'driver_id' => $this->booking->trip->user_id,
            'driver_name' => $driverName,
            'booking_status' => $this->booking->booking_status,
            'event' => $statusKey,
            'type' => match ($this->status) {
                'Accepted' => 'booking_accepted',
                'Rejected' => 'booking_rejected',
                'Started' => 'trip_started',
                'Completed' => 'trip_completed',
                'Cancelled' => 'trip_cancelled',
                default => 'booking_'.$statusKey,
            },
            'title' => $title,
            'icon' => $icon,
            'message' => $message,
            'url' => route('passenger.bookings.show', $this->booking),
        ];
    }
}
