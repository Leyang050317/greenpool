<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driverIds = DB::table('vehicles')
            ->where('status', 'Active')
            ->select('user_id')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('user_id');

        foreach ($driverIds as $driverId) {
            $activeVehicleId = DB::table('vehicles')
                ->where('user_id', $driverId)
                ->where('status', 'Active')
                ->orderByDesc('vehicle_id')
                ->value('vehicle_id');

            DB::table('vehicles')
                ->where('user_id', $driverId)
                ->where('status', 'Active')
                ->where('vehicle_id', '!=', $activeVehicleId)
                ->update([
                    'status' => 'Inactive',
                ]);
        }
    }

    public function down(): void
    {
        // The previous ambiguous multi-active state cannot be restored safely.
    }
};
