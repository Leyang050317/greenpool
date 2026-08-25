<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emergencies', function (Blueprint $table) {
            $table->string('issue_type', 40)->nullable()->after('role');
            $table->index(['trip_id', 'issue_type']);
        });
    }

    public function down(): void
    {
        Schema::table('emergencies', function (Blueprint $table) {
            $table->dropIndex(['trip_id', 'issue_type']);
            $table->dropColumn('issue_type');
        });
    }
};
