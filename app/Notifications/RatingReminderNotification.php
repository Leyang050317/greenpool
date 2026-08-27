<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RatingReminderNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Booking $booking) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $this->booking->loadMissing(['passenger', 'trip.user']);
        $otherPerson = $notifiable->id === $this->booking->passenger_id
            ? $this->booking->trip->user->name
            : $this->booking->passenger->name;

        return [
            'booking_id' => $this->booking->id,
            'type' => 'rating_reminder',
            'title' => 'Share your trip experience',
            'icon' => 'star',
            'message' => 'Rate '.$otherPerson.' while your trip experience is still fresh.',
            'url' => route('ratings.create', $this->booking),
        ];
    }
}
