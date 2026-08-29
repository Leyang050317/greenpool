<?php

namespace App\Console\Commands;

use App\Models\Trip;
use App\Notifications\TripAutoCancelledNotification;
use App\Services\NotificationDeliveryService;
use Illuminate\Console\Command;

class CancelUnbookedDepartedTrips extends Command
{
    protected $signature = 'trips:cancel-unbooked-departed';

    protected $description = 'Cancel scheduled trips that have departed without passenger bookings.';

    public function handle(): int
    {
        $trips = Trip::query()
            ->where('status', 'Scheduled')
            ->where('departure_at', '<', now()->startOfMinute())
            ->whereDoesntHave('bookings')
            ->with('user')
            ->get();

        $delivery = app(NotificationDeliveryService::class);
        foreach ($trips as $trip) {
            $trip->update(['status' => 'Cancelled', 'cancelled_at' => now()]);
            $delivery->send($trip->user, new TripAutoCancelledNotification($trip));
        }

        $cancelled = $trips->count();

        $this->info("Cancelled {$cancelled} unbooked departed trip(s).");

        return self::SUCCESS;
    }
}
