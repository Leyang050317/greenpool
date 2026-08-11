<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            if (! Schema::hasColumn('vehicles', 'vehicle_image_path')) {
                $table->string('vehicle_image_path')->nullable()->after('status');
            }

            if (! Schema::hasColumn('vehicles', 'verification_status')) {
                $table->enum('verification_status', ['Pending', 'Verified', 'Rejected'])
                    ->default('Pending')
                    ->after('vehicle_image_path')
                    ->index();
            }

            if (! Schema::hasColumn('vehicles', 'verified_at')) {
                $table->timestamp('verified_at')->nullable()->after('verification_status');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('vehicles', 'verification_status')) {
            Schema::table('vehicles', function (Blueprint $table) {
                $table->dropIndex(['verification_status']);
            });
        }

        Schema::table('vehicles', function (Blueprint $table) {
            $columns = collect(['vehicle_image_path', 'verification_status', 'verified_at'])
                ->filter(fn (string $column) => Schema::hasColumn('vehicles', $column))
                ->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
