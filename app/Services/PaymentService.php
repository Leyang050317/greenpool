<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;

class PaymentService
{
    public function hasBookingRestriction(User $passenger): bool
    {
        return Payment::query()
            ->where('payer_id', $passenger->id)
            ->where(function ($query) {
                $query->where('payment_status', 'Under Review')
                    ->orWhere(function ($pending) {
                        $pending->where('payment_status', 'Pending')
                            ->where(function ($restriction) {
                                $restriction->where('issue_resolution', 'Kept due')
                                    ->orWhere('created_at', '<=', now()->subDay());
                            });
                    });
            })
            ->exists();
    }

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
