<?php

namespace App\Notifications;

use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class MessageReceivedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Message $message) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $this->message->loadMissing('sender');

        return [
            'message_id' => $this->message->id,
            'booking_id' => $this->message->booking_id,
            'type' => 'message_received',
            'title' => 'New message from '.$this->message->sender->name,
            'icon' => 'message-square',
            'message' => Str::limit($this->message->message, 110),
            'url' => route('bookings.chat.show', $this->message->booking_id),
        ];
    }
}
