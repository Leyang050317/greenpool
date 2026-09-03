<?php

namespace App\Notifications;

use App\Models\Trip;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TripAutoCancelledNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Trip $trip,
        private readonly bool $expired = false,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $route = $this->trip->departure_location.' to '.$this->trip->destination;

        if ($this->expired) {
            return [
                'trip_id' => $this->trip->trip_id,
                'type' => 'trip_auto_expired',
                'title' => 'Trip Expired',
                'icon' => 'clock',
                'message' => "Your trip from {$route} expired because it was not started by the departure time.",
                'url' => route('driver.trips.show', $this->trip),
            ];
        }

        return [
            'trip_id' => $this->trip->trip_id,
            'type' => 'trip_auto_cancelled',
            'title' => 'Trip Cancelled',
            'icon' => 'circle-x',
            'message' => "Your trip from {$route} was cancelled after its departure time passed.",
            'url' => route('driver.trips.show', $this->trip),
        ];
    }
}
