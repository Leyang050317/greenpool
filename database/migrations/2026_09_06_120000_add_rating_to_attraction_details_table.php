<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attraction_details', function (Blueprint $table) {
            $table->decimal('rating', 2, 1)->nullable()->after('category');
            $table->unsignedBigInteger('user_rating_count')->nullable()->after('rating');
        });
    }

    public function down(): void
    {
        Schema::table('attraction_details', function (Blueprint $table) {
            $table->dropColumn(['rating', 'user_rating_count']);
        });
    }
};
