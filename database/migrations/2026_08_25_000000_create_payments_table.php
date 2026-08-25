<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('payer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('payee_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('payment_method', 30)->nullable();
            $table->string('payment_status', 20)->default('Pending');
            $table->string('transaction_reference', 40)->nullable()->unique();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['payer_id', 'payment_status']);
            $table->index(['payee_id', 'payment_status']);
        });

        DB::table('bookings')
            ->join('trips', 'bookings.trip_id', '=', 'trips.trip_id')
            ->where('bookings.booking_status', 'Accepted')
            ->where('trips.status', 'Completed')
            ->select([
                'bookings.id as booking_id',
                'bookings.passenger_id as payer_id',
                'bookings.number_of_seats',
                'trips.user_id as payee_id',
                'trips.price_per_passenger',
            ])
            ->orderBy('bookings.id')
            ->get()
            ->each(function ($booking): void {
                DB::table('payments')->insert([
                    'booking_id' => $booking->booking_id,
                    'payer_id' => $booking->payer_id,
                    'payee_id' => $booking->payee_id,
                    'amount' => (float) $booking->price_per_passenger * (int) $booking->number_of_seats,
                    'payment_status' => 'Pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
