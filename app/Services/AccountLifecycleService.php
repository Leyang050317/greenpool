<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Trip;
use App\Models\User;
use App\Notifications\BookingRequestNotification;
use App\Notifications\BookingStatusNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountLifecycleService
{
    /**
     * Stop future activity without destroying the user's historical records.
     */
    public function deactivate(User $user): void
    {
        if ($user->trips()->where('status', 'In Progress')->exists()) {
            throw ValidationException::withMessages([
                'account' => 'You cannot deactivate your account while a trip is in progress. Complete the trip first.',
            ]);
        }

        [$driverBookings, $passengerBookings] = DB::transaction(function () use ($user): array {
            $driverBookings = collect();
            $passengerBookings = collect();

            if ($user->role === 'driver') {
                $trips = $user->trips()
                    ->where('status', 'Scheduled')
                    ->lockForUpdate()
                    ->get();

                foreach ($trips as $trip) {
                    $trip->update(['status' => 'Cancelled', 'cancelled_at' => now()]);

                    $bookings = $trip->bookings()
                        ->whereIn('booking_status', ['Pending', 'Accepted'])
                        ->lockForUpdate()
                        ->get();

                    foreach ($bookings as $booking) {
                        $booking->update(['booking_status' => 'Cancelled']);
                    }

                    $driverBookings = $driverBookings->merge($bookings);
                }

                $user->vehicles()->update(['status' => 'Inactive']);
            } else {
                $bookings = $user->bookings()
                    ->whereIn('booking_status', ['Pending', 'Accepted'])
                    ->whereHas('trip', fn ($query) => $query->where('status', 'Scheduled'))
                    ->with('trip.user')
                    ->lockForUpdate()
                    ->get();

                foreach ($bookings as $booking) {
                    $booking->update(['booking_status' => 'Cancelled']);
                }

                $passengerBookings = $bookings;
            }

            $user->forceFill([
                'account_status' => 'deactivated',
                'deactivated_at' => now(),
                'remember_token' => null,
            ])->save();

            return [$driverBookings, $passengerBookings];
        });

        /** @var Collection<int, Booking> $driverBookings */
        foreach ($driverBookings as $booking) {
            $booking->loadMissing('passenger');
            $booking->passenger?->notify(new BookingStatusNotification(
                $booking,
                'Cancelled',
                'Your scheduled trip was cancelled because the driver deactivated their account.'
            ));
        }

        /** @var Collection<int, Booking> $passengerBookings */
        foreach ($passengerBookings as $booking) {
            $booking->loadMissing('trip.user');
            $booking->trip?->user?->notify(new BookingRequestNotification($booking, 'cancelled'));
        }
    }
}
