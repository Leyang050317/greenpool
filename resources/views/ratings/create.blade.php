@php
    $suggestions = $reviewee->role === 'driver' ? [
        1 => ['Unsafe driving', 'Very late pickup', 'Smoke smell in vehicle', 'Vehicle was dirty', 'Poor route communication'],
        2 => ['Driving needs improvement', 'Pickup was late', 'Smoke smell in vehicle', 'Vehicle was dirty', 'Communication could be better'],
        3 => ['Average driving', 'Pickup was acceptable', 'Vehicle was okay', 'Route was acceptable'],
        4 => ['Safe driving', 'Arrived on time', 'Clean vehicle', 'Good route communication'],
        5 => ['Excellent and safe driving', 'Very punctual', 'Spotless vehicle', 'Outstanding communication'],
    ] : [
        1 => ['Passenger was very late', 'Disrespectful behaviour', 'Poor communication', 'Left the vehicle untidy'],
        2 => ['Passenger arrived late', 'Behaviour needs improvement', 'Communication could be better', 'Did not keep the vehicle clean'],
        3 => ['Average passenger', 'Arrival time was acceptable', 'Communication was adequate', 'Kept the vehicle reasonably tidy'],
        4 => ['Passenger arrived on time', 'Friendly and respectful', 'Good communication', 'Kept the vehicle clean'],
        5 => ['Passenger was very punctual', 'Excellent and respectful passenger', 'Outstanding communication', 'Left the vehicle spotless'],
    ];
    $totalAmount = (float) $booking->trip->price_per_passenger * $booking->number_of_seats;
@endphp
<x-ratings.shell>
    <div class="min-h-screen bg-[#F8FAFC] px-4 py-10" x-data='{ score: {{ old('score', 0) }}, comment: @json(old('comment', '')), selectedFeedback: [], suggestions: @json($suggestions), toggleFeedback(item) { this.selectedFeedback = this.selectedFeedback.includes(item) ? this.selectedFeedback.filter(value => value !== item) : [...this.selectedFeedback, item]; this.comment = this.selectedFeedback.join(". "); } }'>
        <div class="mx-auto max-w-xl">
            <a href="{{ url()->previous() }}" class="text-sm font-medium text-slate-500">← Back</a>
            <div class="mt-7"><h1 class="text-2xl font-bold text-slate-900">Rate {{ ucfirst($reviewee->role) }}</h1><p class="mt-1 text-sm text-slate-400">Share your experience to help the community.</p></div>
            <form method="POST" action="{{ route('ratings.store',$booking) }}" class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">@csrf
                <div class="flex items-center gap-4 rounded-xl bg-slate-50 p-4"><div class="flex h-11 w-11 items-center justify-center rounded-full bg-green-600 text-sm font-bold text-white">{{ strtoupper(substr($reviewee->name,0,2)) }}</div><div class="min-w-0"><div class="truncate font-semibold">{{ $reviewee->name }}</div><div class="text-xs text-slate-400">{{ ucfirst($reviewee->role) }} · {{ $booking->trip->completed_at->format('j M Y') }}</div></div></div>
                <fieldset class="mt-7 text-center"><legend class="w-full text-sm font-semibold text-slate-700">How was your {{ $reviewee->role === 'driver' ? 'ride' : 'passenger' }}?</legend><div class="mt-3 flex justify-center gap-1 sm:gap-2">@for($i=1;$i<=5;$i++)<button type="button" @click="if (score !== {{$i}}) { selectedFeedback = []; comment = '' }; score={{$i}}" class="text-4xl leading-none transition hover:scale-110 sm:text-5xl" :class="score >= {{$i}} ? 'text-amber-400' : 'text-slate-200'" aria-label="{{$i}} stars">★</button>@endfor</div><input type="hidden" name="score" :value="score"><p class="mt-3 text-sm font-semibold text-green-600" x-text="['','Poor','Fair','Good','Very good','Excellent'][score]"></p>@error('score')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror</fieldset>
                <div x-show="score > 0" x-cloak class="mt-6 border-t border-slate-100 pt-5"><p class="text-sm font-semibold text-slate-700">Quick feedback <span class="font-normal text-slate-400">(select one or more)</span></p><div class="mt-3 flex flex-wrap gap-2"><template x-for="suggestion in (suggestions[score] || [])" :key="suggestion"><button type="button" @click="toggleFeedback(suggestion)" class="rounded-full border px-3 py-2 text-xs font-medium transition" :class="selectedFeedback.includes(suggestion) ? 'border-green-500 bg-green-50 text-green-700' : 'border-slate-200 text-slate-600 hover:bg-slate-50'" :aria-pressed="selectedFeedback.includes(suggestion)" x-text="suggestion"></button></template></div><label for="comment" class="mt-5 block text-sm font-semibold text-slate-700">Comments &amp; Feedback <span class="font-normal text-slate-400">(optional)</span></label><textarea id="comment" name="comment" x-model="comment" maxlength="300" rows="4" class="mt-2 w-full rounded-xl border-slate-300 text-sm focus:border-green-500 focus:ring-green-500" placeholder="Select quick comments or write your own..."></textarea><div class="mt-1 text-right text-xs text-slate-400"><span x-text="comment.length"></span> / 300</div>@error('comment')<p class="text-sm text-red-600">{{ $message }}</p>@enderror</div>
                <div class="mt-6 flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-4 py-3"><div><p class="text-xs text-slate-400">{{ $booking->number_of_seats }} seat(s) × RM {{ number_format((float) $booking->trip->price_per_passenger, 2) }}</p><p class="text-sm font-semibold text-slate-700">Total amount</p></div><strong class="text-lg text-[#2E7D32]">RM {{ number_format($totalAmount, 2) }}</strong></div>
                <button class="mt-5 w-full rounded-xl bg-[#22C55E] py-3 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-40" :disabled="score===0">Submit Rating</button>
            </form>
        </div>
    </div>
</x-ratings.shell>
