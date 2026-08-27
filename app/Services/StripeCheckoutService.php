<?php

namespace App\Services;

use App\Models\Payment;
use RuntimeException;
use Stripe\Checkout\Session;
use Stripe\StripeClient;
use Stripe\Webhook;

class StripeCheckoutService
{
    public function __construct(private readonly PaymentCompletionService $paymentCompletionService) {}

    public function createCheckout(Payment $payment): string
    {
        $payment->loadMissing(['payer', 'payee', 'booking.trip']);

        $session = $this->client()->checkout->sessions->create([
            'mode' => 'payment',
            'payment_method_types' => ['card', 'grabpay', 'alipay'],
            'customer_email' => $payment->payer->email,
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => 'myr',
                    'unit_amount' => (int) round((float) $payment->amount * 100),
                    'product_data' => [
                        'name' => 'GreenPool ride payment',
                        'description' => $payment->booking->trip->departure_location.' to '.$payment->booking->trip->destination,
                    ],
                ],
            ]],
            'metadata' => [
                'payment_id' => (string) $payment->id,
                'booking_id' => (string) $payment->booking_id,
            ],
            'payment_intent_data' => [
                'metadata' => [
                    'payment_id' => (string) $payment->id,
                    'booking_id' => (string) $payment->booking_id,
                ],
            ],
            'success_url' => route('payments.stripe.success').'?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('payments.show', $payment).'?stripe=cancelled',
        ]);

        if (! is_string($session->url) || $session->url === '') {
            throw new RuntimeException('Stripe did not return a checkout URL.');
        }

        $payment->update([
            'payment_status' => 'Pending',
            'payment_method' => 'stripe',
            'stripe_checkout_session_id' => $session->id,
        ]);

        return $session->url;
    }

    public function fulfillCheckout(string $sessionId): Payment
    {
        $session = $this->client()->checkout->sessions->retrieve($sessionId, []);
        $paymentId = (int) ($session->metadata->payment_id ?? 0);
        $payment = Payment::query()->with(['payer', 'payee', 'booking.trip'])->findOrFail($paymentId);

        // Ignore an older Checkout Session after the passenger switches to cash
        // or starts a newer Stripe Checkout Session for the same payment.
        if ($payment->stripe_checkout_session_id !== $session->id) {
            return $payment;
        }

        $expectedAmount = (int) round((float) $payment->amount * 100);
        if ($session->currency !== 'myr' || (int) $session->amount_total !== $expectedAmount) {
            throw new RuntimeException('Stripe checkout amount does not match the GreenPool payment.');
        }

        if ($session->payment_status !== Session::PAYMENT_STATUS_PAID) {
            return $payment;
        }

        $paymentIntentId = is_string($session->payment_intent) ? $session->payment_intent : null;
        $reference = $paymentIntentId ?: $session->id;

        $this->paymentCompletionService->complete(
            $payment,
            'stripe',
            $reference,
            $session->id,
            $paymentIntentId,
        );

        return $payment->refresh();
    }

    public function handleWebhook(string $payload, string $signature): void
    {
        $webhookSecret = (string) config('services.stripe.webhook_secret');
        if ($webhookSecret === '') {
            throw new RuntimeException('Stripe webhook secret is not configured.');
        }

        $event = Webhook::constructEvent($payload, $signature, $webhookSecret);
        $session = $event->data->object;

        if (in_array($event->type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)) {
            $this->fulfillCheckout((string) $session->id);
        }

        if ($event->type === 'checkout.session.async_payment_failed') {
            Payment::query()
                ->where('stripe_checkout_session_id', (string) $session->id)
                ->where('payment_status', 'Pending')
                ->update(['payment_status' => 'Failed']);
        }
    }

    private function client(): StripeClient
    {
        $secret = (string) config('services.stripe.secret');
        if ($secret === '') {
            throw new RuntimeException('Stripe test secret key is not configured.');
        }

        return new StripeClient($secret);
    }
}
