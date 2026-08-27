<?php

namespace App\Http\Controllers;

use App\Events\PaymentReceived;
use App\Http\Requests\StorePaymentRequest;
use App\Mail\PaymentReceiptMail;
use App\Models\Booking;
use App\Models\Payment;
use App\Notifications\PaymentCompletedNotification;
use App\Notifications\PaymentReceivedNotification;
use App\Notifications\RatingReminderNotification;
use App\Services\FareRecommendationService;
use App\Services\NotificationDeliveryService;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly FareRecommendationService $fareRecommendationService,
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

        $payment->loadMissing(['payee', 'booking.trip']);
        $methods = Payment::METHODS;
        $fareBreakdown = $this->fareBreakdown($payment);

        return view('payments.checkout', compact('payment', 'methods', 'fareBreakdown'));
    }

    public function store(StorePaymentRequest $request, Payment $payment): RedirectResponse
    {
        $method = $request->validated('payment_method');

        $payment = DB::transaction(function () use ($payment, $method): Payment {
            $lockedPayment = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();
            abort_if($lockedPayment->isPaid(), 409, 'This booking has already been paid.');
            abort_unless($lockedPayment->payment_status === 'Pending', 422, 'This payment cannot be processed.');

            $lockedPayment->update([
                'payment_method' => $method,
                'payment_status' => 'Paid',
                'transaction_reference' => $this->transactionReference(),
                'paid_at' => now(),
            ]);

            return $lockedPayment->refresh()->load(['payer', 'payee', 'booking.trip']);
        });

        $payment->payee->notify(new PaymentReceivedNotification($payment));
        PaymentReceived::dispatch($payment);
        $this->notificationDelivery->send($payment->payer, new PaymentCompletedNotification($payment));
        if (! $payment->booking->ratings()->where('reviewer_id', $payment->payer_id)->exists()) {
            $this->notificationDelivery->send($payment->payer, new RatingReminderNotification($payment->booking));
        }

        $emailSent = true;
        try {
            Mail::to($payment->payer->email)->send(new PaymentReceiptMail($payment, $this->fareBreakdown($payment)));
        } catch (\Throwable $exception) {
            $emailSent = false;
            report($exception);
        }

        return redirect()->route('payments.show', $payment)
            ->with('success', $emailSent
                ? 'Payment completed. Your E-Receipt was sent to '.$payment->payer->email.'.'
                : 'Payment completed. The E-Receipt email could not be sent, but it remains available here.');
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

    private function transactionReference(): string
    {
        do {
            $reference = 'GP-'.now()->format('Ymd').'-'.Str::upper(Str::random(10));
        } while (Payment::where('transaction_reference', $reference)->exists());

        return $reference;
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
