<x-payments.shell>
    <div class="min-h-[100dvh] bg-slate-50 px-4 py-8 pb-10 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-5xl">
            <a href="{{ route('payments.show', $payment) }}" class="inline-flex items-center gap-2 text-sm font-semibold text-slate-500 transition hover:text-green-700">
                <x-icons.lucide name="arrow-left" class="h-4 w-4" />
                Back to payment
            </a>

            <div class="mt-6 overflow-hidden rounded-3xl bg-white shadow-lg shadow-slate-200/70 ring-1 ring-slate-200">
                <x-trip-static-map :trip="$payment->booking->trip" :route="$mapRoute" />
            </div>

            <section class="mt-3 overflow-hidden rounded-3xl border border-slate-200/80 bg-white shadow-xl shadow-slate-200/60">
                <div class="relative overflow-hidden bg-[#14532d] p-6 text-white sm:p-7 lg:p-8">
                    <div class="absolute -right-20 -top-20 h-56 w-56 rounded-full bg-green-400/20 blur-2xl"></div>
                    <div class="absolute -bottom-24 -left-16 h-64 w-64 rounded-full bg-emerald-300/10 blur-2xl"></div>

                    <div class="relative grid gap-5 lg:grid-cols-[1fr_1.25fr] lg:items-center">
                        <div>
                        <div class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1.5 text-xs font-bold uppercase tracking-wider text-green-100 ring-1 ring-white/15">
                            <x-icons.lucide name="circle-check" class="h-4 w-4" />
                            Ride completed
                        </div>
                        <h1 class="mt-4 text-3xl font-bold leading-tight">Complete your ride payment</h1>
                        <p class="mt-2 text-sm leading-6 text-green-100">Pay securely by card or pay your driver directly in cash.</p>
                        </div>

                        <div class="rounded-2xl bg-white/10 p-5 ring-1 ring-white/15 backdrop-blur-sm">
                            <div class="flex gap-4">
                                <div class="flex flex-col items-center pt-1">
                                    <span class="h-3 w-3 rounded-full border-2 border-green-200 bg-green-400"></span>
                                    <span class="my-1 h-9 w-px bg-white/25"></span>
                                    <span class="h-3 w-3 rounded-full border-2 border-green-200 bg-white"></span>
                                </div>
                                <div class="min-w-0 flex-1 space-y-5">
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wide text-green-200">Pickup</p>
                                        <p class="mt-1 break-words font-semibold text-white [overflow-wrap:anywhere]">{{ $payment->booking->trip->departure_location }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs font-semibold uppercase tracking-wide text-green-200">Destination</p>
                                        <p class="mt-1 break-words font-semibold text-white [overflow-wrap:anywhere]">{{ $payment->booking->trip->destination }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <dl class="grid grid-cols-2 gap-3 lg:col-span-2 lg:grid-cols-3 lg:-mt-1">
                            <div class="rounded-2xl bg-white/10 p-4 ring-1 ring-white/10">
                                <dt class="text-xs font-medium text-green-200">Driver</dt>
                                <dd class="mt-1 break-words text-sm font-bold [overflow-wrap:anywhere]">{{ $payment->payee->name }}</dd>
                            </div>
                            <div class="rounded-2xl bg-white/10 p-4 ring-1 ring-white/10">
                                <dt class="text-xs font-medium text-green-200">Seats</dt>
                                <dd class="mt-1 text-sm font-bold">{{ $payment->booking->number_of_seats }}</dd>
                            </div>
                            <div class="col-span-2 rounded-2xl bg-white/15 p-4 ring-1 ring-white/15 lg:col-span-1">
                                <dt class="text-xs font-medium text-green-200">Total amount</dt>
                                <dd class="mt-1 text-2xl font-extrabold tracking-tight">RM {{ number_format((float) $payment->amount, 2) }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                <div class="border-t border-slate-100 p-6 sm:p-7 lg:p-8">
                    <p class="text-xs font-bold uppercase tracking-wider text-green-700">Payment method</p>
                    <h2 class="mt-2 text-2xl font-bold text-slate-900">How would you like to pay?</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-500">Both methods keep your payment record connected to this ride.</p>

                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                        <form method="POST" action="{{ route('payments.checkout.initiate', $payment->booking) }}">
                            @csrf
                            <input type="hidden" name="method" value="stripe">
                            <button type="submit" class="group flex w-full items-center gap-4 rounded-2xl border-2 border-green-200 bg-green-50/50 p-5 text-left transition hover:-translate-y-0.5 hover:border-green-500 hover:bg-green-50 hover:shadow-lg hover:shadow-green-100">
                                <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-green-600 text-white shadow-sm"><x-icons.lucide name="credit-card" class="h-6 w-6" /></span>
                                <span class="min-w-0 flex-1">
                                    <span class="flex items-center gap-2">
                                        <span class="text-base font-bold text-slate-900">Pay by Card</span>
                                        <span class="rounded-full bg-green-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-green-700">Secure</span>
                                    </span>
                                    <span class="mt-1 block text-sm leading-5 text-slate-500">Credit or debit card via Stripe Checkout</span>
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

                    <div class="mt-5 flex items-start gap-3 rounded-2xl bg-slate-50 p-4 text-sm leading-6 text-slate-500">
                        <span class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white text-green-700 shadow-sm"><x-icons.lucide name="shield-check" class="h-4 w-4" /></span>
                        <p><strong class="font-semibold text-slate-700">Protected payment flow.</strong> Card payments are verified by Stripe. Cash only becomes paid after your driver confirms receipt.</p>
                    </div>
                </div>

            </section>
        </div>
    </div>
</x-payments.shell>
