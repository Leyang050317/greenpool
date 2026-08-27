<x-payments.shell>
    <div class="min-h-screen bg-[radial-gradient(circle_at_top_left,_#dcfce7_0,_#f8fafc_32rem)] px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-5xl">
            <a href="{{ route('payments.show', $payment) }}" class="inline-flex items-center gap-2 text-sm font-semibold text-slate-500 transition hover:text-green-700">
                <x-icons.lucide name="arrow-left" class="h-4 w-4" />
                Back to payment
            </a>

            <section class="mt-6 overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-xl shadow-slate-200/60 lg:grid lg:grid-cols-[0.9fr_1.1fr]">
                <div class="relative overflow-hidden bg-[#14532d] p-6 text-white sm:p-8 lg:p-10">
                    <div class="absolute -right-20 -top-20 h-56 w-56 rounded-full bg-green-400/20 blur-2xl"></div>
                    <div class="absolute -bottom-24 -left-16 h-64 w-64 rounded-full bg-emerald-300/10 blur-2xl"></div>

                    <div class="relative">
                        <div class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1.5 text-xs font-bold uppercase tracking-wider text-green-100 ring-1 ring-white/15">
                            <x-icons.lucide name="circle-check" class="h-4 w-4" />
                            Ride completed
                        </div>
                        <h1 class="mt-6 text-3xl font-bold leading-tight">Complete your ride payment</h1>
                        <p class="mt-3 text-sm leading-6 text-green-100">Choose a secure online payment or pay your driver directly in cash.</p>

                        <div class="mt-8 rounded-2xl bg-white/10 p-5 ring-1 ring-white/15 backdrop-blur-sm">
                            <div class="flex gap-4">
                                <div class="flex flex-col items-center pt-1">
                                    <span class="h-3 w-3 rounded-full border-2 border-green-200 bg-green-400"></span>
                                    <span class="my-1 h-9 w-px bg-white/25"></span>
                                    <span class="h-3 w-3 rounded-full border-2 border-green-200 bg-white"></span>
                                </div>
                                <div class="min-w-0 flex-1 space-y-5">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wide text-green-200">Pickup</p>
                                        <p class="mt-1 truncate font-semibold text-white">{{ $payment->booking->trip->departure_location }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wide text-green-200">Destination</p>
                                        <p class="mt-1 truncate font-semibold text-white">{{ $payment->booking->trip->destination }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <dl class="mt-6 grid grid-cols-2 gap-3">
                            <div class="rounded-2xl bg-white/10 p-4 ring-1 ring-white/10">
                                <dt class="text-xs font-medium text-green-200">Driver</dt>
                                <dd class="mt-1 truncate text-sm font-bold">{{ $payment->payee->name }}</dd>
                            </div>
                            <div class="rounded-2xl bg-white/10 p-4 ring-1 ring-white/10">
                                <dt class="text-xs font-medium text-green-200">Seats</dt>
                                <dd class="mt-1 text-sm font-bold">{{ $payment->booking->number_of_seats }}</dd>
                            </div>
                        </dl>

                        <div class="mt-7 border-t border-white/15 pt-6">
                            <p class="text-sm text-green-200">Total amount</p>
                            <p class="mt-1 text-4xl font-extrabold tracking-tight">RM {{ number_format((float) $payment->amount, 2) }}</p>
                        </div>
                    </div>
                </div>

                <div class="p-6 sm:p-8 lg:p-10">
                    <p class="text-xs font-bold uppercase tracking-wider text-green-700">Payment method</p>
                    <h2 class="mt-2 text-2xl font-bold text-slate-900">How would you like to pay?</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-500">Both methods keep your payment record connected to this ride.</p>

                    <div class="mt-7 space-y-4">
                        <form method="POST" action="{{ route('payments.checkout.initiate', $payment->booking) }}">
                            @csrf
                            <input type="hidden" name="method" value="stripe">
                            <button type="submit" class="group flex w-full items-center gap-4 rounded-2xl border-2 border-green-200 bg-green-50/50 p-5 text-left transition hover:-translate-y-0.5 hover:border-green-500 hover:bg-green-50 hover:shadow-lg hover:shadow-green-100">
                                <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-green-600 text-white shadow-sm"><x-icons.lucide name="credit-card" class="h-6 w-6" /></span>
                                <span class="min-w-0 flex-1">
                                    <span class="flex items-center gap-2">
                                        <span class="text-base font-bold text-slate-900">Pay Online</span>
                                        <span class="rounded-full bg-green-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-green-700">Secure</span>
                                    </span>
                                    <span class="mt-1 block text-sm leading-5 text-slate-500">Continue to Stripe Checkout</span>
                                    <span class="mt-3 flex flex-wrap gap-2">
                                        @foreach(['Cards', 'GrabPay', 'Alipay'] as $method)
                                            <span class="rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-600">{{ $method }}</span>
                                        @endforeach
                                    </span>
                                </span>
                                <x-icons.lucide name="arrow-right" class="h-5 w-5 text-slate-300 transition group-hover:translate-x-1 group-hover:text-green-600" />
                            </button>
                        </form>

                        <form method="POST" action="{{ route('payments.checkout.initiate', $payment->booking) }}">
                            @csrf
                            <input type="hidden" name="method" value="cash">
                            <button type="submit" class="group flex w-full items-center gap-4 rounded-2xl border-2 border-slate-200 bg-white p-5 text-left transition hover:-translate-y-0.5 hover:border-amber-400 hover:bg-amber-50/60 hover:shadow-lg hover:shadow-amber-100">
                                <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-amber-100 text-amber-700"><x-icons.lucide name="banknote" class="h-6 w-6" /></span>
                                <span class="min-w-0 flex-1">
                                    <span class="text-base font-bold text-slate-900">Pay Cash</span>
                                    <span class="mt-1 block text-sm leading-5 text-slate-500">Hand RM {{ number_format((float) $payment->amount, 2) }} to your driver</span>
                                    <span class="mt-2 inline-flex items-center gap-1.5 text-xs font-semibold text-amber-700"><x-icons.lucide name="clock" class="h-3.5 w-3.5" /> Driver confirmation required</span>
                                </span>
                                <x-icons.lucide name="arrow-right" class="h-5 w-5 text-slate-300 transition group-hover:translate-x-1 group-hover:text-amber-600" />
                            </button>
                        </form>
                    </div>

                    <div class="mt-7 flex items-start gap-3 rounded-2xl bg-slate-50 p-4 text-sm leading-6 text-slate-500">
                        <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white text-green-700 shadow-sm"><x-icons.lucide name="shield-check" class="h-4 w-4" /></span>
                        <p><strong class="font-semibold text-slate-700">Protected payment flow.</strong> Online payments are verified by Stripe. Cash only becomes paid after your driver confirms receipt.</p>
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-payments.shell>
