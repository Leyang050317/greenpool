<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('trips')->where('status', 'Active')->update(['status' => 'Scheduled']);

        Schema::table('trips', function (Blueprint $table) {
            $table->enum('status', ['Scheduled', 'In Progress', 'Completed', 'Cancelled'])->default('Scheduled')->change();
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->enum('status', ['Scheduled', 'Active', 'In Progress', 'Completed', 'Cancelled'])->default('Scheduled')->change();
        });
    }
};
