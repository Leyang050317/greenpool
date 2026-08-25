<x-ratings.shell>
    <div class="relative flex min-h-[calc(100dvh-4rem)] items-start justify-center bg-[#F8FAFC] px-4 py-10 sm:items-center sm:py-12" x-data="{ toastVisible: true }">
        <section class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 text-center shadow-sm sm:p-8" aria-labelledby="rating-submitted-title">
            <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-green-100 text-green-600">
                <x-icons.lucide name="circle-check" class="h-9 w-9" />
            </div>
            <h1 id="rating-submitted-title" class="mt-5 text-xl font-bold text-slate-900">Rating Submitted!</h1>
            <p class="mx-auto mt-2 max-w-sm text-sm leading-6 text-slate-500">Thank you for rating {{ $rating->reviewee->name }}. Your feedback helps build a better GreenPool community.</p>
            <div class="mt-4 text-3xl leading-none text-amber-400" aria-label="{{ $rating->score }} out of 5 stars">{{ str_repeat('★', $rating->score) }}<span class="text-slate-200">{{ str_repeat('★', 5 - $rating->score) }}</span></div>
            @if($nextBooking)
                <a href="{{ route('ratings.create', $nextBooking) }}" class="mt-7 block w-full rounded-xl bg-[#22C55E] px-5 py-3 text-sm font-bold text-white hover:bg-green-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-green-600 focus-visible:ring-offset-2">Rate Next Passenger</a>
                <a href="{{ route('ratings.pending') }}" class="mt-3 block text-sm font-semibold text-slate-500 hover:text-slate-700">View all waiting ratings</a>
            @else
                <a href="{{ route('ratings.history') }}" class="mt-7 block w-full rounded-xl bg-[#22C55E] px-5 py-3 text-sm font-bold text-white hover:bg-green-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-green-600 focus-visible:ring-offset-2">Done</a>
            @endif
        </section>

        <div x-show="toastVisible" x-transition x-init="setTimeout(() => toastVisible = false, 5000)" class="fixed bottom-5 left-1/2 z-50 flex w-[calc(100%-2rem)] max-w-sm -translate-x-1/2 items-center gap-3 rounded-xl bg-slate-900 px-4 py-3 text-sm font-medium text-white shadow-xl" role="status">
            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-green-600"><x-icons.lucide name="circle-check" class="h-4 w-4" /></span>
            <span class="min-w-0 flex-1">Rating submitted successfully.</span>
            <button type="button" @click="toastVisible = false" class="rounded p-1 text-slate-300 hover:bg-white/10 hover:text-white" aria-label="Dismiss notification"><x-icons.lucide name="x" class="h-4 w-4" /></button>
        </div>
    </div>
</x-ratings.shell>
