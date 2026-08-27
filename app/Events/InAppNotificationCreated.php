<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class InAppNotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /** @param array<string, mixed> $notification */
    public function __construct(public User $recipient, public array $notification) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel($this->recipient->role.'.'.$this->recipient->id);
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return $this->notification;
    }
}
