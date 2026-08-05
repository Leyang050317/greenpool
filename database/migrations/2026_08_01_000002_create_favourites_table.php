<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favourites', function (Blueprint $table) {
            $table->id('favourite_id');
            $table->foreignId('attraction_id')
                ->constrained('tourist_attractions', 'attraction_id')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['attraction_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favourites');
    }
};
