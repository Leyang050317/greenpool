<x-payments.shell>
    @php
        $isPaid = $payment->isPaid();
        $isUnderReview = $payment->isUnderReview();
    @endphp
    <div class="min-h-screen bg-[#F8FAFC] px-4 py-8 sm:px-6 lg:px-8" data-payment-detail data-payment-id="{{ $payment->id }}" data-payment-status="{{ $payment->payment_status }}">
        <div class="mx-auto max-w-xl">
            <a href="{{ route('payments.index') }}" class="text-sm font-medium text-slate-500 hover:text-slate-700">← Back to Payments</a>
            @if(request('stripe') === 'cancelled')
                <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800">Stripe Checkout was cancelled. No payment was taken, and you can try again.</div>
            @endif
            <section class="mt-7 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
                <div class="text-center">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full {{ $isPaid ? 'bg-green-100 text-green-600' : ($isUnderReview ? 'bg-blue-100 text-blue-600' : ($payment->isWaived() ? 'bg-slate-100 text-slate-600' : 'bg-amber-100 text-amber-600')) }}"><x-icons.lucide :name="$isPaid ? 'circle-check' : ($isUnderReview ? 'message-square' : 'clock')" class="h-9 w-9" /></div>
                    <h1 class="mt-4 text-xl font-bold text-slate-900">{{ $isPaid ? (Auth::id() === $payment->payee_id ? 'Payment Received' : 'Payment Successful!') : ($isUnderReview ? 'Payment issue under review' : ($payment->isWaived() ? 'Payment waived' : 'Payment Pending')) }}</h1>
                    <p class="mt-1 text-sm text-slate-500">{{ $isPaid ? (Auth::id() === $payment->payee_id ? 'This payment is included in your Total Earnings.' : 'Your payment has been recorded.') : ($isUnderReview ? 'New bookings are paused while you and the other trip participant discuss and resolve this issue.' : ($payment->isWaived() ? 'No payment is required for this trip.' : ($payment->payment_method === 'cash' ? (Auth::id() === $payment->payee_id ? 'Confirm only after you have received the cash from the passenger.' : 'Pay the driver in cash. Your driver will confirm after receiving it.') : 'This completed ride is waiting for payment.'))) }}</p>
                    <p class="mt-5 text-3xl font-bold text-[#2E7D32]">RM {{ number_format((float) $payment->amount, 2) }}</p>
                </div>

                <dl class="mt-7 divide-y divide-slate-100 rounded-xl bg-slate-50 px-4">
                    @foreach([
                        'Status' => $payment->payment_status,
                        'Passenger' => $payment->payer->name,
                        'Driver' => $payment->payee->name,
                        'Route' => $payment->booking->trip->departure_location.' to '.$payment->booking->trip->destination,
                        'Payment method' => $payment->methodLabel(),
                        'Transaction reference' => $payment->transaction_reference ?? 'Not available yet',
                        'Paid at' => $payment->paid_at?->format('j M Y, g:i A') ?? 'Not paid yet',
                    ] as $label => $value)
                        <div class="flex flex-col gap-1 py-3 sm:flex-row sm:items-start sm:justify-between sm:gap-6"><dt class="text-sm text-slate-400">{{ $label }}</dt><dd class="break-all text-sm font-semibold text-slate-700 sm:text-right">{{ $value }}</dd></div>
                    @endforeach
                </dl>

                <div class="mt-5 rounded-xl border border-slate-200 px-4 py-3">
                    <h2 class="text-sm font-bold text-slate-800">Payment summary</h2>
                    <dl class="mt-2 space-y-2 text-sm">
                        <div class="flex justify-between gap-4"><dt class="text-slate-500">Price per passenger</dt><dd class="font-semibold text-slate-700">RM {{ number_format((float) $payment->booking->trip->price_per_passenger, 2) }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-slate-500">Seats</dt><dd class="font-semibold text-slate-700">{{ $payment->booking->number_of_seats }}</dd></div>
                        <div class="flex justify-between gap-4 border-t border-slate-100 pt-2"><dt class="font-bold text-slate-800">Total</dt><dd class="font-bold text-[#2E7D32]">RM {{ number_format((float) $payment->amount, 2) }}</dd></div>
                    </dl>
                </div>

                <div class="mt-5 rounded-xl border border-green-100 bg-green-50 px-4 py-3 text-sm text-green-900">
                    <p class="font-semibold">Trip chat remains available</p>
                    <p class="mt-1 text-green-800">Use the trip chat to discuss a payment issue with the other participant.</p>
                    <a href="{{ route('bookings.chat.show', $payment->booking) }}" class="mt-3 inline-flex font-semibold underline">Open trip chat</a>
                </div>

                @if(Auth::id() === $payment->payer_id && $payment->payment_status === 'Pending')
                    <form method="POST" action="{{ route('payments.issue.report', $payment) }}" class="mt-6 rounded-2xl border border-blue-200 bg-blue-50 p-4 sm:p-5">
                        @csrf
                        <h2 class="font-bold text-slate-900">Report payment issue</h2>
                        <p class="mt-1 text-sm text-slate-600">This pauses new bookings while you and the driver review the payment. It does not automatically cancel the payment.</p>
                        <select name="issue_reason" required class="mt-4 block w-full rounded-xl border-slate-200 text-sm"><option value="">Select a reason</option><option value="not_boarded">I did not board this trip</option><option value="ended_early">The trip ended early</option><option value="amount_incorrect">The amount is incorrect</option><option value="duplicate_charge">I was charged twice</option><option value="other">Other</option></select>
                        <textarea name="issue_details" rows="3" maxlength="1000" class="mt-3 block w-full rounded-xl border-slate-200 text-sm" placeholder="Explain what happened (optional)"></textarea>
                        <button class="mt-3 rounded-xl border border-blue-300 bg-white px-4 py-2 text-sm font-semibold text-blue-800 hover:bg-blue-100">Report payment issue</button>
                    </form>
                @elseif(Auth::id() === $payment->payee_id && $isUnderReview)
                    <form method="POST" action="{{ route('payments.issue.resolve', $payment) }}" class="mt-6 rounded-2xl border border-blue-200 bg-blue-50 p-4 sm:p-5">
                        @csrf
                        <h2 class="font-bold text-slate-900">Review payment issue</h2>
                        <p class="mt-1 text-sm text-slate-600">Review the trip chat before deciding. This decision is recorded.</p>
                        <textarea name="resolution_details" rows="3" maxlength="1000" class="mt-4 block w-full rounded-xl border-slate-200 text-sm" placeholder="Reason for your decision (optional)"></textarea>
                        <div class="mt-3 flex flex-wrap gap-3"><button name="resolution" value="waive" class="rounded-xl bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700">Waive payment</button><button name="resolution" value="keep_due" class="rounded-xl border border-amber-300 bg-white px-4 py-2 text-sm font-semibold text-amber-800 hover:bg-amber-100">Keep payment due</button></div>
                    </form>
                @endif

                @if(Auth::id() === $payment->payee_id && !$isPaid && $payment->payment_method === 'cash')
                    <div id="cash-confirmation" class="mt-6 scroll-mt-24 rounded-2xl border border-amber-200 bg-amber-50 p-4 sm:p-5">
                        <div class="flex items-start gap-3">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-700"><x-icons.lucide name="banknote" class="h-5 w-5" /></span>
                            <div>
                                <h2 class="font-bold text-slate-900">Cash payment</h2>
                                <p class="mt-1 text-sm text-slate-600">Collect RM {{ number_format((float) $payment->amount, 2) }} and confirm when received.</p>
                            </div>
                        </div>
                        <form method="POST" action="{{ route('payments.cash.confirm', $payment) }}" class="mt-4">
                            @csrf
                            <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-xl bg-[#16803c] py-3 text-sm font-bold text-white shadow-sm hover:bg-[#126b32]">
                                <x-icons.lucide name="circle-check" class="h-5 w-5" />
                                Confirm Cash Received
                            </button>
                        </form>
                    </div>
                @elseif(Auth::id() === $payment->payer_id && !$isPaid)
                    <a href="{{ route('payments.checkout', $payment->booking) }}" class="mt-6 block w-full rounded-xl bg-[#22C55E] py-3 text-center text-sm font-bold text-white hover:bg-green-600">{{ $payment->payment_method === 'cash' ? 'Change Payment Method' : 'Pay Now' }}</a>
                @elseif(Auth::id() === $payment->payer_id && $isPaid && !$payment->booking->ratings->contains('reviewer_id', Auth::id()))
                    <a href="{{ route('ratings.create', $payment->booking) }}" class="mt-6 block w-full rounded-xl bg-[#22C55E] py-3 text-center text-sm font-bold text-white hover:bg-green-600">Rate Driver</a>
                @endif
                @if($isPaid)
                    <a href="{{ route(Auth::user()->role === 'driver' ? 'driver.home' : 'passenger.home') }}" class="mt-3 block w-full rounded-xl bg-[#22C55E] py-3 text-center text-sm font-bold text-white hover:bg-green-600">Back to Dashboard</a>
                    <a href="{{ route('payments.receipt', $payment) }}" class="mt-3 block w-full rounded-xl border border-slate-200 py-3 text-center text-sm font-bold text-slate-700 hover:bg-slate-50">View E-Receipt</a>
                @endif
            </section>
        </div>
    </div>
</x-payments.shell>
