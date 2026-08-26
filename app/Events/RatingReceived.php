<?php

namespace App\Events;

use App\Models\Rating;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RatingReceived implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public Rating $rating)
    {
        $this->rating->loadMissing(['reviewer', 'reviewee']);
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel($this->rating->reviewee->role.'.'.$this->rating->reviewee_id);
    }

    public function broadcastWith(): array
    {
        return [
            'rating_id' => $this->rating->id,
            'reviewer_name' => $this->rating->reviewer->name,
            'score' => $this->rating->score,
            'title' => 'New rating received',
            'message' => $this->rating->reviewer->name.' gave you a '.$this->rating->score.'-star rating.',
            'url' => route('ratings.received', $this->rating->reviewee),
            'created_at' => $this->rating->created_at?->toIso8601String(),
        ];
    }
}
