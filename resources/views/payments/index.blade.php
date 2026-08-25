<x-payments.shell>
    @php
        $isDriver = Auth::user()->role === 'driver';
        $statusClasses = [
            'Pending' => 'bg-amber-50 text-amber-700',
            'Paid' => 'bg-green-50 text-green-700',
            'Failed' => 'bg-red-50 text-red-700',
            'Refunded' => 'bg-slate-100 text-slate-600',
        ];
    @endphp
    <div class="min-h-screen bg-[#F8FAFC] px-4 py-8 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-5xl">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Payments</h1>
                <p class="mt-1 text-sm text-slate-500">{{ $isDriver ? 'Track payments received from your passengers.' : 'Pay for completed rides and view your receipts.' }}</p>
            </div>

            <div class="mt-7 grid gap-4 sm:grid-cols-3">
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-sm text-slate-400">Pending</p><p class="mt-2 text-2xl font-bold text-slate-900">{{ $stats['pending'] }}</p></div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-sm text-slate-400">Paid</p><p class="mt-2 text-2xl font-bold text-slate-900">{{ $stats['paid'] }}</p></div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-sm text-slate-400">{{ $isDriver ? 'Total received' : 'Total paid' }}</p><p class="mt-2 text-2xl font-bold text-[#2E7D32]">RM {{ number_format($stats['total'], 2) }}</p></div>
            </div>

            <form method="GET" class="mt-6 flex flex-wrap gap-2" aria-label="Filter payments">
                @foreach(['' => 'All', 'Pending' => 'Pending', 'Paid' => 'Paid'] as $value => $label)
                    <button name="status" value="{{ $value }}" class="rounded-full border px-4 py-2 text-sm font-semibold {{ request('status', '') === $value ? 'border-green-600 bg-green-600 text-white' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' }}">{{ $label }}</button>
                @endforeach
            </form>

            <div class="mt-5 space-y-3">
                @forelse($payments as $payment)
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-green-100 text-sm font-bold text-green-700">RM</div>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="font-semibold text-slate-900">{{ $isDriver ? $payment->payer->name : $payment->payee->name }}</h2>
                                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusClasses[$payment->payment_status] ?? 'bg-slate-100 text-slate-600' }}">{{ $payment->payment_status }}</span>
                                </div>
                                <p class="mt-1 truncate text-sm text-slate-500">{{ $payment->booking->trip->departure_location }} to {{ $payment->booking->trip->destination }}</p>
                                <p class="mt-1 text-xs text-slate-400">{{ $payment->booking->number_of_seats }} seat(s) · Completed {{ $payment->booking->trip->completed_at?->format('j M Y') }}</p>
                            </div>
                            <div class="sm:text-right">
                                <p class="text-lg font-bold text-slate-900">RM {{ number_format((float) $payment->amount, 2) }}</p>
                                @if(!$isDriver && $payment->payment_status === 'Pending')
                                    <a href="{{ route('payments.checkout', $payment->booking) }}" class="mt-2 inline-flex rounded-xl bg-[#22C55E] px-5 py-2.5 text-sm font-bold text-white hover:bg-green-600">Pay Now</a>
                                @else
                                    <a href="{{ route('payments.show', $payment) }}" class="mt-2 inline-flex rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50">View Details</a>
                                @endif
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="rounded-2xl border border-slate-200 bg-white px-6 py-16 text-center">
                        <p class="font-semibold text-slate-700">No payments found.</p>
                        <p class="mt-1 text-sm text-slate-400">Payments are generated after an accepted trip is completed.</p>
                    </div>
                @endforelse
            </div>
            <div class="mt-6">{{ $payments->links() }}</div>
        </div>
    </div>
</x-payments.shell>
