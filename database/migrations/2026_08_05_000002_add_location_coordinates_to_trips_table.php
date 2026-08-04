<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->decimal('departure_latitude', 10, 7)->nullable()->after('departure_location');
            $table->decimal('departure_longitude', 10, 7)->nullable()->after('departure_latitude');
            $table->decimal('destination_latitude', 10, 7)->nullable()->after('destination');
            $table->decimal('destination_longitude', 10, 7)->nullable()->after('destination_latitude');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn(['departure_latitude', 'departure_longitude', 'destination_latitude', 'destination_longitude']);
        });
    }
};
