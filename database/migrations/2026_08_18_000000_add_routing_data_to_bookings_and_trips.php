<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('pickup_place_id')->nullable()->after('pickup_point');
            $table->decimal('pickup_latitude', 10, 7)->nullable()->after('pickup_place_id');
            $table->decimal('pickup_longitude', 10, 7)->nullable()->after('pickup_latitude');
            $table->unsignedTinyInteger('pickup_sequence')->nullable()->after('pickup_longitude');
            $table->index(['trip_id', 'pickup_sequence']);
        });

        Schema::table('trips', function (Blueprint $table) {
            $table->timestamp('estimated_arrival_at')->nullable()->after('estimated_duration_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['trip_id', 'pickup_sequence']);
            $table->dropColumn(['pickup_place_id', 'pickup_latitude', 'pickup_longitude', 'pickup_sequence']);
        });

        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn('estimated_arrival_at');
        });
    }
};
