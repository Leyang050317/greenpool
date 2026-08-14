<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('driver.{driverId}', function (User $user, int $driverId): bool {
    return $user->role === 'driver' && $user->id === $driverId;
});

Broadcast::channel('passenger.{passengerId}', function (User $user, int $passengerId): bool {
    return $user->role === 'passenger' && $user->id === $passengerId;
});
