<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_places_usage', function (Blueprint $table) {
            $table->id();
            $table->date('usage_date')->unique();
            $table->unsignedInteger('request_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_places_usage');
    }
};
