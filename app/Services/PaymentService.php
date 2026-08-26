<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Payment;

class PaymentService
{
    public function createPendingForBooking(Booking $booking): Payment
    {
        $booking->loadMissing('trip');
        abort_unless(
            $booking->booking_status === 'Accepted' && $booking->trip->status === 'Completed',
            422,
            'Payment is available only after a completed accepted booking.'
        );

        return Payment::firstOrCreate(
            ['booking_id' => $booking->id],
            [
                'payer_id' => $booking->passenger_id,
                'payee_id' => $booking->trip->user_id,
                'amount' => (float) $booking->trip->price_per_passenger * $booking->number_of_seats,
                'payment_status' => 'Pending',
            ]
        );
    }
}
