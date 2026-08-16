<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columns = [
            'front_image_path' => fn (Blueprint $table) => $table->string('front_image_path')->nullable(),
            'rear_image_path' => fn (Blueprint $table) => $table->string('rear_image_path')->nullable(),
            'side_image_path' => fn (Blueprint $table) => $table->string('side_image_path')->nullable(),
            'vehicle_geran_path' => fn (Blueprint $table) => $table->string('vehicle_geran_path')->nullable(),
            'driving_licence_path' => fn (Blueprint $table) => $table->string('driving_licence_path')->nullable(),
            'voc_reference_no' => fn (Blueprint $table) => $table->string('voc_reference_no')->nullable(),
            'registered_owner_name' => fn (Blueprint $table) => $table->string('registered_owner_name')->nullable(),
            'owner_identity_no' => fn (Blueprint $table) => $table->string('owner_identity_no', 12)->nullable(),
            'owner_address' => fn (Blueprint $table) => $table->text('owner_address')->nullable(),
            'chassis_no' => fn (Blueprint $table) => $table->string('chassis_no')->nullable(),
            'engine_no' => fn (Blueprint $table) => $table->string('engine_no')->nullable(),
            'manufacturer' => fn (Blueprint $table) => $table->string('manufacturer')->nullable(),
            'model_name' => fn (Blueprint $table) => $table->string('model_name')->nullable(),
            'engine_capacity' => fn (Blueprint $table) => $table->unsignedInteger('engine_capacity')->nullable(),
            'fuel_type' => fn (Blueprint $table) => $table->string('fuel_type')->nullable(),
            'origin_status' => fn (Blueprint $table) => $table->string('origin_status')->nullable(),
            'usage_class' => fn (Blueprint $table) => $table->string('usage_class')->nullable(),
            'body_type' => fn (Blueprint $table) => $table->string('body_type')->nullable(),
            'manufacturing_year' => fn (Blueprint $table) => $table->string('manufacturing_year', 4)->nullable(),
            'registration_date' => fn (Blueprint $table) => $table->date('registration_date')->nullable(),
            'licence_name' => fn (Blueprint $table) => $table->string('licence_name')->nullable(),
            'licence_identity_no' => fn (Blueprint $table) => $table->string('licence_identity_no', 12)->nullable(),
            'date_of_birth' => fn (Blueprint $table) => $table->date('date_of_birth')->nullable(),
            'nationality' => fn (Blueprint $table) => $table->string('nationality')->nullable(),
            'licence_class' => fn (Blueprint $table) => $table->string('licence_class')->nullable(),
            'licence_valid_from' => fn (Blueprint $table) => $table->date('licence_valid_from')->nullable(),
            'licence_valid_until' => fn (Blueprint $table) => $table->date('licence_valid_until')->nullable(),
            'licence_address' => fn (Blueprint $table) => $table->text('licence_address')->nullable(),
        ];

        foreach ($columns as $column => $definition) {
            if (! Schema::hasColumn('vehicles', $column)) {
                Schema::table('vehicles', $definition);
            }
        }
    }

    public function down(): void
    {
        Schema::table('vehicles', fn (Blueprint $table) => $table->dropColumn([
            'front_image_path', 'rear_image_path', 'side_image_path', 'vehicle_geran_path',
            'driving_licence_path', 'voc_reference_no', 'registered_owner_name', 'owner_identity_no',
            'owner_address', 'chassis_no', 'engine_no', 'manufacturer', 'model_name', 'engine_capacity',
            'fuel_type', 'origin_status', 'usage_class', 'body_type', 'manufacturing_year',
            'registration_date', 'licence_name', 'licence_identity_no', 'date_of_birth', 'nationality',
            'licence_class', 'licence_valid_from', 'licence_valid_until', 'licence_address',
        ]));
    }
};
