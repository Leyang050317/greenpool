<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Receipt · GreenPool</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#F4F7F5] text-slate-900">
<main class="px-4 py-8 sm:px-6 sm:py-12">
    <div class="mx-auto max-w-2xl">
        <nav class="mb-5 flex flex-wrap items-center justify-between gap-3 text-sm">
            <a href="{{ route('payments.show', $payment) }}" class="font-medium text-slate-500 hover:text-slate-800">← Back to Payment</a>
            <a href="{{ route(Auth::user()->role === 'driver' ? 'driver.home' : 'passenger.home') }}" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 font-bold text-slate-700 shadow-sm hover:bg-slate-50">Back to Dashboard</a>
        </nav>

        <article class="overflow-hidden rounded-3xl border border-emerald-100 bg-white shadow-xl shadow-emerald-950/5">
            <header class="relative overflow-hidden bg-gradient-to-br from-[#1B5E20] via-[#2E7D32] to-[#22A447] px-6 py-8 text-white sm:px-9">
                <div class="absolute -right-12 -top-14 h-44 w-44 rounded-full border-[28px] border-white/10"></div>
                <div class="absolute -bottom-16 right-24 h-36 w-36 rounded-full bg-white/5"></div>
                <div class="relative flex items-start justify-between gap-5">
                    <div>
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/15 backdrop-blur"><x-icons.lucide name="car-front" class="h-7 w-7" /></div>
                        <h1 class="mt-5 text-2xl font-bold">GreenPool E-Receipt</h1>
                        <p class="mt-1 text-sm text-green-100">Your ride payment is complete.</p>
                    </div>
                    <div class="text-right">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-bold backdrop-blur"><x-icons.lucide name="circle-check" class="h-4 w-4" /> Paid</span>
                        <p class="mt-5 text-xs font-medium uppercase tracking-wider text-green-100">Total paid</p>
                        <p class="mt-1 text-3xl font-bold">RM {{ number_format((float) $payment->amount, 2) }}</p>
                    </div>
                </div>
            </header>

            <div class="p-6 sm:p-9">
                <div class="rounded-2xl border border-green-100 bg-green-50 px-4 py-3 text-sm text-green-800">
                    <div class="flex items-start gap-3"><x-icons.lucide name="mail" class="mt-0.5 h-5 w-5 shrink-0" /><div><strong class="block">E-Receipt sent automatically</strong><span class="text-green-700">A copy was sent to {{ $payment->payer->email }} after payment.</span></div></div>
                </div>

                <section class="mt-7 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-2xl bg-slate-50 p-4"><p class="text-xs font-medium uppercase tracking-wide text-slate-400">Receipt number</p><p class="mt-2 break-all text-sm font-bold text-slate-800">{{ $payment->transaction_reference }}</p></div>
                    <div class="rounded-2xl bg-slate-50 p-4"><p class="text-xs font-medium uppercase tracking-wide text-slate-400">Paid at</p><p class="mt-2 text-sm font-bold text-slate-800">{{ $payment->paid_at?->format('j M Y, g:i A') }}</p></div>
                </section>

                <section class="mt-7">
                    <h2 class="text-sm font-bold uppercase tracking-wide text-slate-400">Your journey</h2>
                    <div class="mt-3 rounded-2xl border border-slate-200 p-5">
                        <div class="grid grid-cols-[20px_1fr] gap-x-3 gap-y-1">
                            <span class="mt-1.5 h-3 w-3 rounded-full border-4 border-green-200 bg-green-600"></span><div><p class="text-xs text-slate-400">From</p><p class="font-semibold text-slate-800">{{ $payment->booking->trip->departure_location }}</p></div>
                            <span class="ml-[5px] h-8 border-l-2 border-dashed border-slate-200"></span><span></span>
                            <span class="mt-1.5 h-3 w-3 rounded-full bg-slate-800"></span><div><p class="text-xs text-slate-400">To</p><p class="font-semibold text-slate-800">{{ $payment->booking->trip->destination }}</p></div>
                        </div>
                        @if($fareBreakdown)<div class="mt-5 flex items-center gap-2 border-t border-slate-100 pt-4 text-sm text-slate-500"><x-icons.lucide name="route" class="h-4 w-4" /> {{ number_format($fareBreakdown['distance_km'], 2) }} km driving distance</div>@endif
                    </div>
                </section>

                <section class="mt-7">
                    <h2 class="text-sm font-bold uppercase tracking-wide text-slate-400">Payment details</h2>
                    <dl class="mt-3 divide-y divide-slate-100 rounded-2xl bg-slate-50 px-5">
                        @foreach([
                            'Passenger' => $payment->payer->name,
                            'Driver' => $payment->payee->name,
                            'Payment method' => $payment->methodLabel(),
                            'Price per passenger' => 'RM '.number_format((float) $payment->booking->trip->price_per_passenger, 2),
                            'Seats booked' => $payment->booking->number_of_seats,
                        ] as $label => $value)
                            <div class="flex items-center justify-between gap-5 py-3.5"><dt class="text-sm text-slate-500">{{ $label }}</dt><dd class="text-right text-sm font-bold text-slate-800">{{ $value }}</dd></div>
                        @endforeach
                        <div class="flex items-center justify-between gap-5 py-4"><dt class="font-bold text-slate-900">Total</dt><dd class="text-xl font-bold text-[#2E7D32]">RM {{ number_format((float) $payment->amount, 2) }}</dd></div>
                    </dl>
                </section>

                <p class="mt-8 text-center text-xs text-slate-400">Thank you for riding with GreenPool.</p>
            </div>
        </article>
    </div>
</main>
</body>
</html>
