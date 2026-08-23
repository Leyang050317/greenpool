<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->unsignedInteger('bdm')->nullable();
            $table->unsignedInteger('bgk')->nullable();
            $table->unsignedInteger('btm')->nullable();
            $table->string('registration_condition_1')->nullable();
            $table->string('registration_condition_2')->nullable();
            $table->string('registration_condition_3')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', fn (Blueprint $table) => $table->dropColumn([
            'bdm', 'bgk', 'btm', 'registration_condition_1', 'registration_condition_2', 'registration_condition_3',
        ]));
    }
};
