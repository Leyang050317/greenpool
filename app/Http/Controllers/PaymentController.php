<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Payment;
use App\Notifications\CashPaymentSelectedNotification;
use App\Services\FareRecommendationService;
use App\Services\NotificationDeliveryService;
use App\Services\PaymentCompletionService;
use App\Services\PaymentService;
use App\Services\StripeCheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly PaymentCompletionService $paymentCompletionService,
        private readonly FareRecommendationService $fareRecommendationService,
        private readonly StripeCheckoutService $stripeCheckoutService,
        private readonly NotificationDeliveryService $notificationDelivery,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $query = Payment::query()
            ->with(['payer', 'payee', 'booking.trip'])
            ->where($user->role === 'driver' ? 'payee_id' : 'payer_id', $user->id);

        if ($request->filled('status') && in_array($request->input('status'), ['Pending', 'Paid', 'Failed', 'Refunded'], true)) {
            $query->where('payment_status', $request->input('status'));
        }

        $payments = $query->latest()->paginate(8)->withQueryString();
        $base = Payment::query()->where($user->role === 'driver' ? 'payee_id' : 'payer_id', $user->id);
        $stats = [
            'pending' => (clone $base)->where('payment_status', 'Pending')->count(),
            'paid' => (clone $base)->where('payment_status', 'Paid')->count(),
            'total' => (float) (clone $base)->where('payment_status', 'Paid')->sum('amount'),
        ];

        return view('payments.index', compact('payments', 'stats'));
    }

    public function checkout(Request $request, Booking $booking): View|RedirectResponse
    {
        abort_unless($request->user()->role === 'passenger' && $booking->passenger_id === $request->user()->id, 403);
        $payment = $this->paymentService->createPendingForBooking($booking);

        if ($payment->isPaid()) {
            return redirect()->route('payments.show', $payment);
        }

        $payment->loadMissing(['payer', 'payee', 'booking.trip']);

        return view('payments.checkout', compact('payment'));
    }

    public function initiateCheckout(Request $request, Booking $booking): RedirectResponse
    {
        abort_unless($request->user()->role === 'passenger' && $booking->passenger_id === $request->user()->id, 403);
        $validated = $request->validate(['method' => ['required', 'in:stripe,cash']]);
        $payment = $this->paymentService->createPendingForBooking($booking);

        if ($payment->isPaid()) {
            return redirect()->route('payments.show', $payment);
        }

        if ($validated['method'] === 'cash') {
            $cashWasAlreadySelected = $payment->payment_method === 'cash' && $payment->payment_status === 'Pending';
            $payment->update([
                'payment_method' => 'cash',
                'payment_status' => 'Pending',
                'transaction_reference' => null,
                'stripe_checkout_session_id' => null,
                'stripe_payment_intent_id' => null,
                'paid_at' => null,
            ]);

            if (! $cashWasAlreadySelected) {
                $payment->loadMissing(['payer', 'payee', 'booking.trip']);
                $this->notificationDelivery->send($payment->payee, new CashPaymentSelectedNotification($payment));
            }

            return redirect()->route('payments.show', $payment)
                ->with('success', 'Cash selected. Please pay the driver, who must confirm the cash was received.');
        }

        try {
            return redirect()->away($this->stripeCheckoutService->createCheckout($payment));
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()->route('payments.show', $payment)
                ->with('error', 'Stripe Checkout is temporarily unavailable. Please try again.');
        }
    }

    public function confirmCash(Request $request, Payment $payment): RedirectResponse
    {
        abort_unless($request->user()->role === 'driver' && $payment->payee_id === $request->user()->id, 403);
        abort_unless($payment->payment_method === 'cash' && ! $payment->isPaid(), 422);

        $completed = $this->paymentCompletionService->complete(
            $payment,
            'cash',
            'CASH-'.$payment->id,
        );

        return redirect()->route('payments.show', $payment)
            ->with($completed ? 'success' : 'error', $completed
                ? 'Cash payment confirmed. It is now included in your Total Earnings.'
                : 'This cash payment was already confirmed.');
    }

    public function stripeSuccess(Request $request): RedirectResponse
    {
        $sessionId = (string) $request->query('session_id');
        abort_if($sessionId === '', 404);

        $payment = Payment::query()->where('stripe_checkout_session_id', $sessionId)->firstOrFail();
        abort_unless($payment->payer_id === $request->user()->id, 403);

        try {
            $payment = $this->stripeCheckoutService->fulfillCheckout($sessionId);
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()->route('payments.show', $payment)
                ->with('error', 'Stripe is still confirming this payment. The status will update automatically.');
        }

        return redirect()->route('payments.show', $payment)
            ->with($payment->isPaid() ? 'success' : 'error', $payment->isPaid()
                ? 'Stripe payment completed successfully. Your E-Receipt is ready.'
                : 'Stripe is still processing this payment. The status will update automatically.');
    }

    public function show(Request $request, Payment $payment): View
    {
        abort_unless(in_array($request->user()->id, [$payment->payer_id, $payment->payee_id], true), 403);
        $payment->loadMissing(['payer', 'payee', 'booking.trip', 'booking.ratings']);
        $fareBreakdown = $this->fareBreakdown($payment);

        return view('payments.show', compact('payment', 'fareBreakdown'));
    }

    public function receipt(Request $request, Payment $payment): View
    {
        abort_unless(in_array($request->user()->id, [$payment->payer_id, $payment->payee_id], true), 403);
        abort_unless($payment->isPaid(), 404);
        $payment->loadMissing(['payer', 'payee', 'booking.trip']);
        $fareBreakdown = $this->fareBreakdown($payment);

        return view('payments.receipt', compact('payment', 'fareBreakdown'));
    }

    private function fareBreakdown(Payment $payment): ?array
    {
        $distance = $payment->booking->trip->estimated_distance_km;

        return $distance === null ? null : [
            'distance_km' => (float) $distance,
            ...$this->fareRecommendationService->recommend((float) $distance),
        ];
    }
}
