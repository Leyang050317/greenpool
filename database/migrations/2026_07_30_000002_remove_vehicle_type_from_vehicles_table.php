<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('vehicles', 'vehicle_type')) {
            Schema::table('vehicles', function (Blueprint $table) {
                $table->dropColumn('vehicle_type');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('vehicles', 'vehicle_type')) {
            Schema::table('vehicles', function (Blueprint $table) {
                $table->string('vehicle_type', 30)->nullable()->after('colour');
            });
        }
    }
};
