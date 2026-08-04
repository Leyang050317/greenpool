<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->id('trip_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles', 'vehicle_id')->restrictOnDelete();
            $table->string('departure_location');
            $table->string('destination');
            $table->dateTime('departure_at');
            $table->unsignedTinyInteger('available_seats');
            $table->decimal('price_per_passenger', 8, 2)->default(0);
            $table->text('description')->nullable();
            $table->decimal('estimated_distance_km', 7, 2)->nullable();
            $table->enum('status', ['Scheduled', 'In Progress', 'Completed', 'Cancelled'])->default('Scheduled');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status', 'departure_at']);
            $table->index(['vehicle_id', 'departure_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
