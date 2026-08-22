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
        $destination = $this->booking->trip->destination;
        $statusKey = strtolower($this->status);

        $title = match ($this->status) {
            'Accepted' => 'Booking request accepted',
            'Rejected' => 'Booking request rejected',
            'Cancelled' => 'Trip cancelled',
            default => 'Booking updated',
        };

        $icon = match ($this->status) {
            'Accepted' => 'circle-check',
            'Rejected', 'Cancelled' => 'circle-x',
            default => 'bell',
        };

        $message = match ($this->status) {
            'Accepted' => "{$driverName} accepted your booking request to {$destination}.",
            'Rejected' => "{$driverName} rejected your booking request to {$destination}.",
            'Cancelled' => "{$driverName} cancelled the trip to {$destination}.",
            default => "Your booking to {$destination} was updated.",
        };

        return [
            'booking_id' => $this->booking->id,
            'trip_id' => $this->booking->trip_id,
            'driver_id' => $this->booking->trip->user_id,
            'driver_name' => $driverName,
            'booking_status' => $this->status,
            'type' => $this->status === 'Cancelled' ? 'trip_cancelled' : 'booking_request_'.$statusKey,
            'title' => $title,
            'icon' => $icon,
            'message' => $message,
            'url' => route('passenger.bookings.history'),
        ];
    }
}
