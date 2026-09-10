<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->boolean('smoking_allowed')->default(false)->after('description');
            $table->boolean('pets_allowed')->default(false)->after('smoking_allowed');
            $table->string('conversation_preference', 20)->default('Moderate')->after('pets_allowed');
        });

        DB::table('trips')
            ->select(['trip_id', 'user_id'])
            ->chunkById(100, function ($trips): void {
                $preferences = DB::table('driver_preferences')
                    ->whereIn('user_id', $trips->pluck('user_id')->unique())
                    ->get(['user_id', 'smoking_allowed', 'pets_allowed', 'conversation_preference'])
                    ->keyBy('user_id');

                foreach ($trips as $trip) {
                    $preference = $preferences->get($trip->user_id);

                    if ($preference) {
                        DB::table('trips')->where('trip_id', $trip->trip_id)->update([
                            'smoking_allowed' => $preference->smoking_allowed,
                            'pets_allowed' => $preference->pets_allowed,
                            'conversation_preference' => $preference->conversation_preference,
                        ]);
                    }
                }
            }, 'trip_id');
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn(['smoking_allowed', 'pets_allowed', 'conversation_preference']);
        });
    }
};
