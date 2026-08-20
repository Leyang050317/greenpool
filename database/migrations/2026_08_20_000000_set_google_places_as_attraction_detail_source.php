<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attraction_details', function (Blueprint $table) {
            $table->string('source_name', 50)->default('Google Places')->change();
        });
    }

    public function down(): void
    {
        Schema::table('attraction_details', function (Blueprint $table) {
            $table->string('source_name', 50)->default('Google Places')->change();
        });
    }
};
