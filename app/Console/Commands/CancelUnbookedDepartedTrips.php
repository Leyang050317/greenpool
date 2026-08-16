<?php

namespace App\Console\Commands;

use App\Models\Trip;
use Illuminate\Console\Command;

class CancelUnbookedDepartedTrips extends Command
{
    protected $signature = 'trips:cancel-unbooked-departed';

    protected $description = 'Cancel scheduled trips that have departed without passenger bookings.';

    public function handle(): int
    {
        $cancelled = Trip::query()
            ->where('status', 'Scheduled')
            ->where('departure_at', '<=', now())
            ->whereDoesntHave('bookings')
            ->update([
                'status' => 'Cancelled',
                'cancelled_at' => now(),
            ]);

        $this->info("Cancelled {$cancelled} unbooked departed trip(s).");

        return self::SUCCESS;
    }
}
