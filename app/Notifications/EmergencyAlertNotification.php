<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Models\Emergency;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class EmergencyAlertNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Emergency $emergency, private readonly ?Booking $booking = null) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $trip = $this->emergency->trip;
        return [
            'emergency_id' => $this->emergency->id,
            'trip_id' => $trip->trip_id,
            'booking_id' => $this->booking?->id,
            'type' => 'emergency_alert',
            'title' => 'Issue Report',
            'icon' => 'triangle-alert',
            'message' => $this->emergency->notificationMessage(),
            'url' => $this->booking
                ? route('passenger.bookings.show', $this->booking)
                : route('driver.trips.show', $trip),
        ];
    }
}
