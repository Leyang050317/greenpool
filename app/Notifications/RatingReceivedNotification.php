<?php

namespace App\Notifications;

use App\Models\Rating;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RatingReceivedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Rating $rating) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'rating_id' => $this->rating->id,
            'reviewer_name' => $this->rating->reviewer->name,
            'score' => $this->rating->score,
            'message' => $this->rating->reviewer->name.' gave you a '.$this->rating->score.'-star rating.',
            'url' => route('ratings.received', $notifiable),
        ];
    }
}
