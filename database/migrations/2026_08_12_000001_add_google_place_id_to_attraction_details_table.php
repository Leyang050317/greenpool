<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attraction_details', function (Blueprint $table) {
            $table->string('google_place_id', 255)->nullable()->unique()->after('source_place_id');
        });
    }

    public function down(): void
    {
        Schema::table('attraction_details', function (Blueprint $table) {
            $table->dropUnique(['google_place_id']);
            $table->dropColumn('google_place_id');
        });
    }
};
