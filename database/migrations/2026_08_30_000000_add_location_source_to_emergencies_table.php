<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emergencies', function (Blueprint $table) {
            $table->string('location_source')->nullable()->after('longitude');
        });
    }

    public function down(): void
    {
        Schema::table('emergencies', function (Blueprint $table) {
            $table->dropColumn('location_source');
        });
    }
};
