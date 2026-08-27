<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('trip_updates')->default(true);
            $table->boolean('booking_updates')->default(true);
            $table->boolean('payment_updates')->default(true);
            $table->boolean('message_alerts')->default(true);
            $table->boolean('rating_reminders')->default(true);
            $table->boolean('attraction_updates')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
