<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public Message $message)
    {
        $this->message->loadMissing(['sender', 'booking']);
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('booking.'.$this->message->booking_id);
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'booking_id' => $this->message->booking_id,
            'sender_id' => $this->message->sender_id,
            'sender_name' => $this->message->sender->name,
            'message' => $this->message->message,
            'created_at' => $this->message->created_at?->toIso8601String(),
            'created_at_label' => $this->message->created_at?->format('g:i A'),
        ];
    }
}
