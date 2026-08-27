<?php

namespace App\Services;

use App\Events\InAppNotificationCreated;
use App\Models\User;
use Illuminate\Notifications\Notification;

class NotificationDeliveryService
{
    public function send(User $recipient, Notification $notification): void
    {
        $recipient->notify($notification);

        InAppNotificationCreated::dispatch($recipient, $notification->toArray($recipient));
    }
}
