<?php

namespace App\Notifications;

use App\Models\Trip;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TripAutoCancelledNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Trip $trip) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'trip_id' => $this->trip->trip_id,
            'type' => 'trip_auto_cancelled',
            'title' => 'Trip Cancelled',
            'icon' => 'circle-x',
            'message' => 'Your unbooked trip from '.$this->trip->departure_location.' to '.$this->trip->destination.' was cancelled after its departure time passed.',
            'url' => route('driver.trips.show', $this->trip),
        ];
    }
}
