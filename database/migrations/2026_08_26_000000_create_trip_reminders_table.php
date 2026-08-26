<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained('trips', 'trip_id')->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reminder_type', 50);
            $table->timestamp('scheduled_for');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            // A passenger has one booking per trip and the driver has one
            // recipient entry, so this also safely covers the nullable booking.
            $table->unique(['trip_id', 'user_id', 'reminder_type']);
            $table->index(['scheduled_for', 'sent_at']);
            $table->index(['trip_id', 'booking_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_reminders');
    }
};
