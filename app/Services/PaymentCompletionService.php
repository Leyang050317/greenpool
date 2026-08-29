<?php

namespace App\Services;

use App\Events\PaymentReceived;
use App\Mail\PaymentReceiptMail;
use App\Models\Payment;
use App\Notifications\PaymentCompletedNotification;
use App\Notifications\PaymentReceivedNotification;
use App\Notifications\RatingReminderNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class PaymentCompletionService
{
    public function __construct(
        private readonly FareRecommendationService $fareRecommendationService,
        private readonly NotificationDeliveryService $notificationDelivery,
    ) {}

    public function complete(
        Payment $payment,
        string $method,
        string $reference,
        ?string $checkoutSessionId = null,
        ?string $paymentIntentId = null,
    ): bool {
        $completedPayment = DB::transaction(function () use ($payment, $method, $reference, $checkoutSessionId, $paymentIntentId): ?Payment {
            $lockedPayment = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

            if ($lockedPayment->isPaid()) {
                return null;
            }

            $lockedPayment->update([
                'payment_method' => $method,
                'payment_status' => 'Paid',
                'transaction_reference' => $reference,
                'stripe_checkout_session_id' => $checkoutSessionId ?? $lockedPayment->stripe_checkout_session_id,
                'stripe_payment_intent_id' => $paymentIntentId,
                'paid_at' => now(),
            ]);

            return $lockedPayment->refresh()->load(['payer', 'payee', 'booking.trip.vehicle']);
        });

        if (! $completedPayment) {
            return false;
        }

        $completedPayment->payee->notify(new PaymentReceivedNotification($completedPayment));
        PaymentReceived::dispatch($completedPayment);
        $this->notificationDelivery->send($completedPayment->payer, new PaymentCompletedNotification($completedPayment));

        if (! $completedPayment->booking->ratings()->where('reviewer_id', $completedPayment->payer_id)->exists()) {
            $this->notificationDelivery->send($completedPayment->payer, new RatingReminderNotification($completedPayment->booking));
        }

        try {
            Mail::to($completedPayment->payer->email)->send(
                new PaymentReceiptMail($completedPayment, $this->fareBreakdown($completedPayment))
            );
        } catch (\Throwable $exception) {
            report($exception);
        }

        return true;
    }

    private function fareBreakdown(Payment $payment): ?array
    {
        $distance = $payment->booking->trip->estimated_distance_km;

        return $distance === null ? null : [
            'distance_km' => (float) $distance,
            ...$this->fareRecommendationService->recommend(
                (float) $distance,
                $payment->booking->trip->vehicle,
            ),
        ];
    }
}
