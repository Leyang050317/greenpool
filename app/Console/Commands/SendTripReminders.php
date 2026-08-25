<?php

namespace App\Console\Commands;

use App\Events\TripReminderSent;
use App\Models\Booking;
use App\Models\Trip;
use App\Models\TripReminder;
use App\Notifications\TripReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendTripReminders extends Command
{
    protected $signature = 'trips:send-reminders';

    protected $description = 'Send one reminder to trip participants approximately 30 minutes before departure.';

    public function handle(): int
    {
        $now = now();
        $trips = Trip::query()
            ->with(['user', 'bookings' => fn ($bookings) => $bookings
                ->where('booking_status', 'Accepted')
                ->with('passenger')])
            ->where('status', 'Scheduled')
            ->whereNull('started_at')
            ->whereNull('cancelled_at')
            ->whereBetween('departure_at', [$now->copy()->addMinutes(29), $now->copy()->addMinutes(31)])
            ->get();

        $sent = 0;
        foreach ($trips as $trip) {
            foreach ($trip->bookings as $booking) {
                $sent += $this->send($trip, $booking, $booking->passenger_id, 'passenger') ? 1 : 0;
            }
            if ($trip->bookings->isNotEmpty()) {
                $sent += $this->send($trip, null, $trip->user_id, 'driver') ? 1 : 0;
            }
        }

        $this->info("Sent {$sent} trip reminder(s).");

        return self::SUCCESS;
    }

    private function send(Trip $trip, ?Booking $booking, int $userId, string $role): bool
    {
        $reminder = TripReminder::firstOrCreate([
            'trip_id' => $trip->trip_id,
            'user_id' => $userId,
            'reminder_type' => 'departure_30_minutes',
        ], [
            'booking_id' => $booking?->id,
            'scheduled_for' => $trip->departure_at->copy()->subMinutes(30),
        ]);

        if ($reminder->sent_at !== null) {
            return false;
        }

        try {
            $recipient = $booking?->passenger ?? $trip->user;
            $recipient->notify(new TripReminderNotification($trip, $booking));
            $reminder->update(['sent_at' => now()]);
            TripReminderSent::dispatch($trip, $userId, $role, $booking);

            return true;
        } catch (Throwable $exception) {
            Log::warning('Trip reminder could not be sent.', [
                'trip_id' => $trip->trip_id,
                'booking_id' => $booking?->id,
                'user_id' => $userId,
                'exception' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
