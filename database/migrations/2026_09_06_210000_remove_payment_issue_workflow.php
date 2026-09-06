<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notifications')) {
            DB::table('notifications')
                ->where('type', 'App\\Notifications\\PaymentIssueNotification')
                ->delete();
        }

        DB::table('payments')
            ->whereIn('payment_status', ['Under Review', 'Waived'])
            ->update(['payment_status' => 'Pending']);

        if (Schema::hasColumn('payments', 'issue_resolved_by')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropConstrainedForeignId('issue_resolved_by');
            });
        }

        $columns = collect([
            'issue_reason',
            'issue_details',
            'issue_reported_at',
            'issue_resolution',
            'issue_resolution_details',
            'issue_resolved_at',
        ])->filter(fn (string $column) => Schema::hasColumn('payments', $column))->all();

        if ($columns !== []) {
            Schema::table('payments', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('issue_reason', 50)->nullable()->after('payment_status');
            $table->text('issue_details')->nullable()->after('issue_reason');
            $table->timestamp('issue_reported_at')->nullable()->after('issue_details');
            $table->string('issue_resolution', 30)->nullable()->after('issue_reported_at');
            $table->text('issue_resolution_details')->nullable()->after('issue_resolution');
            $table->timestamp('issue_resolved_at')->nullable()->after('issue_resolution_details');
            $table->foreignId('issue_resolved_by')->nullable()->after('issue_resolved_at')->constrained('users')->nullOnDelete();
        });
    }
};
