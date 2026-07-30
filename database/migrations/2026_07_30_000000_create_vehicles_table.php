<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id('vehicle_id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('plate_number', 20)->unique();
            $table->string('brand', 50);
            $table->string('model', 50);
            $table->string('colour', 20);
            $table->unsignedTinyInteger('seat_capacity')->default(4);
            $table->enum('status', ['Active', 'Inactive'])->default('Inactive');

            $table->index('status');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE vehicles ADD CONSTRAINT chk_vehicle_seat_capacity CHECK (seat_capacity BETWEEN 1 AND 4)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
