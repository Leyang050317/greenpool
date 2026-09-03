<?php

namespace App\Console\Commands;

use App\Events\BookingStatusUpdated;
use App\Models\Booking;
use App\Models\Trip;
use App\Notifications\BookingStatusNotification;
use App\Notifications\TripAutoCancelledNotification;
use App\Services\NotificationDeliveryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExpireDepartedTrips extends Command
{
    protected $signature = 'trips:expire-departed';

    protected $description = 'Cancel scheduled trips whose departure time has passed.';

    public function handle(): int
    {
        $trips = Trip::query()
            ->where('status', 'Scheduled')
            ->where('departure_at', '<', now()->startOfMinute())
            ->with('user')
            ->get();

        $delivery = app(NotificationDeliveryService::class);
        $cancelled = 0;

        foreach ($trips as $trip) {
            $hasAccepted = $trip->bookings()
                ->where('booking_status', 'Accepted')
                ->exists();

            $trip->update(['status' => 'Cancelled', 'cancelled_at' => now()]);

            $openBookings = Booking::query()
                ->with(['passenger', 'trip.user'])
                ->where('trip_id', $trip->getKey())
                ->whereIn('booking_status', ['Pending', 'Accepted'])
                ->get();

            $acceptedBookings = $openBookings->where('booking_status', 'Accepted')->values();
            $pendingBookings = $openBookings->where('booking_status', 'Pending')->values();

            $openBookings->each->update(['booking_status' => 'Cancelled']);

            $route = $trip->departure_location.' → '.$trip->destination;

            if ($hasAccepted) {
                // Trip Expired: at least one accepted booking
                foreach ($acceptedBookings as $booking) {
                    try {
                        $delivery->send($booking->passenger, new BookingStatusNotification($booking, 'Expired'));
                    } catch (Throwable $exception) {
                        Log::warning('Auto-expiration passenger notification failed.', [
                            'booking_id' => $booking->id,
                            'trip_id' => $trip->trip_id,
                            'exception' => $exception->getMessage(),
                        ]);
                    }
                }

                foreach ($pendingBookings as $booking) {
                    try {
                        $delivery->send(
                            $booking->passenger,
                            new BookingStatusNotification(
                                $booking,
                                'Cancelled',
                                "Your booking request for {$route} is no longer active because the trip expired."
                            )
                        );
                    } catch (Throwable $exception) {
                        Log::warning('Auto-expiration pending passenger notification failed.', [
                            'booking_id' => $booking->id,
                            'trip_id' => $trip->trip_id,
                            'exception' => $exception->getMessage(),
                        ]);
                    }
                }

                foreach ($openBookings as $booking) {
                    try {
                        $isAccepted = $acceptedBookings->contains('id', $booking->id);
                        BookingStatusUpdated::dispatch(
                            $booking,
                            driverNotificationType: null,
                            passengerNotificationType: $isAccepted ? 'trip_expired' : 'trip_cancelled'
                        );
                    } catch (Throwable $exception) {
                        Log::warning('Trip expired broadcast failed.', [
                            'booking_id' => $booking->id,
                            'trip_id' => $trip->trip_id,
                            'exception' => $exception->getMessage(),
                        ]);
                    }
                }

                $delivery->send($trip->user, new TripAutoCancelledNotification($trip, expired: true));
            } else {
                // Trip Cancelled: pending only or no bookings
                foreach ($pendingBookings as $booking) {
                    try {
                        $delivery->send(
                            $booking->passenger,
                            new BookingStatusNotification(
                                $booking,
                                'Cancelled',
                                "Your booking request for {$route} is no longer active because the trip was cancelled."
                            )
                        );
                    } catch (Throwable $exception) {
                        Log::warning('Auto-cancellation passenger notification failed.', [
                            'booking_id' => $booking->id,
                            'trip_id' => $trip->trip_id,
                            'exception' => $exception->getMessage(),
                        ]);
                    }
                }

                foreach ($pendingBookings as $booking) {
                    try {
                        BookingStatusUpdated::dispatch(
                            $booking,
                            driverNotificationType: null,
                            passengerNotificationType: 'trip_cancelled'
                        );
                    } catch (Throwable $exception) {
                        Log::warning('Trip cancelled broadcast failed.', [
                            'booking_id' => $booking->id,
                            'trip_id' => $trip->trip_id,
                            'exception' => $exception->getMessage(),
                        ]);
                    }
                }

                $delivery->send($trip->user, new TripAutoCancelledNotification($trip, expired: false));
            }

            $cancelled++;
        }

        $this->info("Cancelled {$cancelled} departed trip(s).");

        return self::SUCCESS;
    }
}
