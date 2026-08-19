<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('smoking_allowed')->default(false);
            $table->boolean('pets_allowed')->default(false);
            $table->enum('conversation_preference', ['Quiet', 'Moderate', 'Chatty'])->default('Moderate');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_preferences');
    }
};
