<?php

use App\Models\Booking;
use App\Models\Trip;
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

Broadcast::channel('trip.{tripId}', function (User $user, int $tripId): bool {
    $trip = Trip::query()->find($tripId);

    if (! $trip || $trip->status !== 'In Progress') {
        return false;
    }

    return $trip->user_id === $user->id || Booking::query()
        ->where('trip_id', $trip->trip_id)
        ->where('passenger_id', $user->id)
        ->where('booking_status', 'Accepted')
        ->exists();
});
