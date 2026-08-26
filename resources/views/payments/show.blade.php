<x-payments.shell>
    @php
        $isPaid = $payment->isPaid();
    @endphp
    <div class="min-h-screen bg-[#F8FAFC] px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-xl">
            <a href="{{ route('payments.index') }}" class="text-sm font-medium text-slate-500 hover:text-slate-700">← Back to Payments</a>
            <section class="mt-7 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
                <div class="text-center">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full {{ $isPaid ? 'bg-green-100 text-green-600' : 'bg-amber-100 text-amber-600' }}"><x-icons.lucide :name="$isPaid ? 'circle-check' : 'clock'" class="h-9 w-9" /></div>
                    <h1 class="mt-4 text-xl font-bold text-slate-900">{{ $isPaid ? 'Payment Successful!' : 'Payment Pending' }}</h1>
                    <p class="mt-1 text-sm text-slate-500">{{ $isPaid ? 'Your payment has been recorded.' : 'This completed ride is waiting for payment.' }}</p>
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

                @if($fareBreakdown)
                    <div class="mt-5 rounded-xl border border-slate-200 p-4">
                        <h2 class="text-sm font-bold text-slate-800">Fare breakdown</h2>
                        <dl class="mt-3 space-y-2 text-sm">
                            <div class="flex justify-between"><dt class="text-slate-500">Distance</dt><dd class="font-semibold">{{ number_format($fareBreakdown['distance_km'], 2) }} km</dd></div>
                            <div class="flex justify-between"><dt class="text-slate-500">Suggested per passenger</dt><dd class="font-semibold">RM {{ number_format($fareBreakdown['recommended_price'], 2) }}</dd></div>
                            <div class="flex justify-between"><dt class="text-slate-500">Actual per passenger</dt><dd class="font-semibold">RM {{ number_format((float) $payment->booking->trip->price_per_passenger, 2) }}</dd></div>
                            <div class="flex justify-between"><dt class="text-slate-500">Seats</dt><dd class="font-semibold">{{ $payment->booking->number_of_seats }}</dd></div>
                        </dl>
                    </div>
                @endif

                @if(Auth::id() === $payment->payer_id && !$isPaid)
                    <a href="{{ route('payments.checkout', $payment->booking) }}" class="mt-6 block w-full rounded-xl bg-[#22C55E] py-3 text-center text-sm font-bold text-white hover:bg-green-600">Pay Now</a>
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
