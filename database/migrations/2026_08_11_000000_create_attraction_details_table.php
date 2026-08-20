<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attraction_details', function (Blueprint $table) {
            $table->id('detail_id');
            $table->foreignId('attraction_id')->unique()->constrained('tourist_attractions', 'attraction_id')->cascadeOnDelete();
            $table->string('category', 100)->nullable();
            $table->string('opening_hours', 255)->nullable();
            $table->string('entrance_fee', 100)->nullable();
            $table->string('contact', 100)->nullable();
            $table->string('website', 255)->nullable();
            $table->string('source_name', 50)->default('Google Places');
            $table->string('source_place_id', 255)->nullable()->unique();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('image_attribution', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attraction_details');
    }
};
