<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('vehicles', 'is_default')) {
            Schema::table('vehicles', function (Blueprint $table) {
                $table->dropColumn('is_default');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('vehicles', 'is_default')) {
            Schema::table('vehicles', function (Blueprint $table) {
                $table->boolean('is_default')->default(false)->after('status');
                $table->index('is_default');
            });
        }
    }
};
