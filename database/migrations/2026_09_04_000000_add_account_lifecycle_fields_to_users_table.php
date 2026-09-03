<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('account_status', 24)->default('active')->after('remember_token');
            $table->timestamp('deactivated_at')->nullable()->after('account_status');
            $table->timestamp('permanently_closed_at')->nullable()->after('deactivated_at');
            $table->index('account_status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['account_status']);
            $table->dropColumn(['account_status', 'deactivated_at', 'permanently_closed_at']);
        });
    }
};
