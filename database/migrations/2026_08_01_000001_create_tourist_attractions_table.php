<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tourist_attractions', function (Blueprint $table) {
            $table->id('attraction_id');
            $table->string('attraction_name', 150);
            $table->string('state', 50);
            $table->text('description')->nullable();
            $table->string('location', 255)->nullable();
            $table->string('image_url', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tourist_attractions');
    }
};
