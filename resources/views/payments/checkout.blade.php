<x-payments.shell>
    <div class="min-h-screen bg-[#F8FAFC] px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-xl">
            <a href="{{ route('payments.index') }}" class="text-sm font-medium text-slate-500 hover:text-slate-700">← Back to Payments</a>
            <div class="mt-7">
                <h1 class="text-2xl font-bold text-slate-900">Complete Payment</h1>
                <p class="mt-1 text-sm text-slate-500">Choose how you want to pay for this completed ride.</p>
            </div>

            <form method="POST" action="{{ route('payments.store', $payment) }}" x-data="{ processing: false }" @submit="processing = true" class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                @csrf
                <div class="rounded-xl bg-slate-50 p-4">
                    <div class="flex items-start justify-between gap-4"><div><p class="font-semibold text-slate-900">{{ $payment->payee->name }}</p><p class="mt-1 text-sm text-slate-500">{{ $payment->booking->trip->departure_location }} to {{ $payment->booking->trip->destination }}</p></div><span class="rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-700">Completed</span></div>
                    <div class="mt-4 flex items-end justify-between border-t border-slate-200 pt-4"><div><p class="text-xs text-slate-400">{{ $payment->booking->number_of_seats }} seat(s) × RM {{ number_format((float) $payment->booking->trip->price_per_passenger, 2) }}</p><p class="mt-1 text-sm font-semibold text-slate-700">Total amount</p></div><strong class="text-2xl text-[#2E7D32]">RM {{ number_format((float) $payment->amount, 2) }}</strong></div>
                </div>

                @if($fareBreakdown)
                    <div class="mt-5 rounded-xl border border-slate-200 p-4">
                        <h2 class="text-sm font-bold text-slate-800">Fare breakdown</h2>
                        <dl class="mt-3 space-y-2 text-sm">
                            <div class="flex justify-between gap-4"><dt class="text-slate-500">Driving distance</dt><dd class="font-semibold text-slate-700">{{ number_format($fareBreakdown['distance_km'], 2) }} km</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-slate-500">Route suggestion</dt><dd class="font-semibold text-slate-700">RM {{ number_format($fareBreakdown['base_fare'], 2) }} + {{ number_format($fareBreakdown['distance_km'], 2) }} km × RM {{ number_format($fareBreakdown['rate_per_km'], 2) }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-slate-500">Suggested per passenger</dt><dd class="font-semibold text-green-700">RM {{ number_format($fareBreakdown['recommended_price'], 2) }}</dd></div>
                            <div class="flex justify-between gap-4"><dt class="text-slate-500">Driver's price per passenger</dt><dd class="font-semibold text-slate-700">RM {{ number_format((float) $payment->booking->trip->price_per_passenger, 2) }}</dd></div>
                            <div class="flex justify-between gap-4 border-t border-slate-100 pt-2"><dt class="font-semibold text-slate-700">{{ $payment->booking->number_of_seats }} seat(s) total</dt><dd class="font-bold text-slate-900">RM {{ number_format((float) $payment->amount, 2) }}</dd></div>
                        </dl>
                    </div>
                @endif

                <fieldset class="mt-6">
                    <legend class="text-sm font-bold text-slate-800">Payment method</legend>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2">
                        @foreach($methods as $value => $label)
                            <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 p-4 transition has-[:checked]:border-green-500 has-[:checked]:bg-green-50">
                                <input type="radio" name="payment_method" value="{{ $value }}" class="border-slate-300 text-green-600 focus:ring-green-500" {{ old('payment_method') === $value ? 'checked' : '' }}>
                                <span class="text-sm font-semibold text-slate-700">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('payment_method')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                </fieldset>

                <button type="submit" :disabled="processing" class="mt-5 w-full rounded-xl bg-[#22C55E] py-3 text-sm font-bold text-white hover:bg-green-600 disabled:cursor-wait disabled:opacity-60"><span x-text="processing ? 'Processing payment...' : 'Pay RM {{ number_format((float) $payment->amount, 2) }}'"></span></button>
            </form>
        </div>
    </div>
</x-payments.shell>
