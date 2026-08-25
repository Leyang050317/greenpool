<?php

use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('driver.{driverId}', function (User $user, int $driverId): bool {
    return $user->role === 'driver' && $user->id === $driverId;
});

Broadcast::channel('passenger.{passengerId}', function (User $user, int $passengerId): bool {
    return $user->role === 'passenger' && $user->id === $passengerId;
});

Broadcast::channel('booking.{bookingId}', function (User $user, int $bookingId): bool {
    $booking = Booking::query()->with('trip')->find($bookingId);

    return $booking !== null
        && ($user->id === $booking->passenger_id || $user->id === $booking->trip->user_id);
});
