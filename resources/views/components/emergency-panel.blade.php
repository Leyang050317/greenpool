@props(['trip'])

@php
    $contacts = auth()->user()->emergencyContacts()->get();
    $emergencyContactsRoute = auth()->user()->role === 'driver' ? route('driver.profile.edit') : route('profile.edit');
    $reports = $trip->emergencies()->with(['user', 'acknowledgedBy', 'resolvedBy'])->latest('triggered_at')->get();
    $hasOpenEmergency = $reports->contains(fn ($report) => in_array($report->status, ['Active', 'Acknowledged'], true));
    $isDriver = $trip->user_id === auth()->id();
    $hasPickedUpPassenger = $trip->bookings()->where('booking_status', 'Accepted')->whereNotNull('picked_up_at')->exists();
@endphp

<section
    x-data="{
        open: false,
        reported: false,
        submitting: false,
        errorMessage: '',
        openPanel() {
            this.open = true;
            this.reported = false;
            this.submitting = false;
            this.errorMessage = '';
        },
    }"
    x-on:greenpool:emergency-reported.window="reported = true; submitting = false"
    x-on:greenpool:emergency-report-failed.window="submitting = false; errorMessage = $event.detail?.message || 'We could not submit the emergency report. Please try again.'"
    @keydown.escape.window="if (open && !submitting) open = false"
    data-emergency-panel
    data-trip-id="{{ $trip->trip_id }}"
    class="rounded-2xl border border-red-200 bg-red-50 p-5"
    aria-label="Report emergency"
>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="flex items-center gap-2 text-sm font-semibold text-red-900">
                <x-icons.lucide name="triangle-alert" class="h-4 w-4" />
                Emergency
            </h2>
            <p class="mt-1 text-xs leading-5 text-red-800">Use this only for a genuine emergency during an active trip.</p>
        </div>
        <button data-emergency-report-button type="button" @click="openPanel()" class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl border border-red-200 bg-white px-4 py-2 text-sm font-semibold text-red-700 shadow-sm hover:bg-red-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2">
            <x-icons.lucide name="triangle-alert" class="h-4 w-4" />
            {{ $hasOpenEmergency ? 'Report another emergency' : 'Report Emergency' }}
        </button>
    </div>

    @if($reports->isNotEmpty())
        <div class="mt-5 space-y-3 border-t border-red-200 pt-4" aria-label="Emergency reports">
            <p class="text-xs font-semibold uppercase tracking-wide text-red-800">Trip emergency reports</p>
            @foreach($reports as $report)
                <article id="emergency-{{ $report->id }}" data-emergency-report data-emergency-status="{{ $report->status }}" class="rounded-xl border border-red-100 bg-white p-4 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="font-semibold text-slate-900">{{ $report->issueLabel() }}</p>
                            <p class="mt-1 text-xs text-slate-500">Reported by {{ $report->user->name }} · {{ $report->triggered_at->format('j M, g:i A') }}</p>
                        </div>
                        <span data-emergency-status-for="{{ $report->id }}" class="rounded-full px-2 py-1 text-xs font-semibold {{ $report->status === 'Resolved' ? 'bg-green-100 text-green-700' : ($report->status === 'Acknowledged' ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700') }}">{{ $report->status }}</span>
                    </div>
                    @if($report->description)<p class="mt-3 text-sm leading-6 text-slate-700">{{ $report->description }}</p>@endif
                    <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-slate-600">
                        <span><x-icons.lucide name="map-pin" class="mr-1 inline h-3.5 w-3.5" />{{ $report->locationStatusLabel() }}</span>
                        @if($report->mapUrl())<a href="{{ $report->mapUrl() }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-[#16803c] underline underline-offset-2">Open location in Maps</a>@endif
                    </div>
                    <p data-emergency-acknowledged-meta-for="{{ $report->id }}" class="mt-3 text-xs text-amber-800 {{ $report->status === 'Acknowledged' && $report->acknowledgedBy ? '' : 'hidden' }}">@if($report->acknowledgedBy)Acknowledged by {{ $report->acknowledgedBy->name }} · {{ $report->acknowledged_at?->format('j M, g:i A') }}@endif</p>
                    <p data-emergency-resolved-meta-for="{{ $report->id }}" class="mt-3 text-xs text-green-800 {{ $report->status === 'Resolved' && $report->resolvedBy ? '' : 'hidden' }}">@if($report->resolvedBy)Resolved by {{ $report->resolvedBy->name }} · {{ $report->resolved_at?->format('j M, g:i A') }}@endif</p>
                    @php
                        $mayResolve = $isDriver || ($report->role === 'passenger' && $report->user_id === auth()->id());
                    @endphp
                    @if($report->status === 'Active' && $report->user_id !== auth()->id())
                        <form data-emergency-acknowledge-for="{{ $report->id }}" method="POST" action="{{ route('emergencies.acknowledge', $report) }}" class="mt-4">@csrf @method('PATCH')<button class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-800 hover:bg-amber-100">Acknowledge emergency</button></form>
                    @endif
                    @if($mayResolve)
                        <form data-emergency-resolve-for="{{ $report->id }}" method="POST" action="{{ route('emergencies.resolve', $report) }}" class="mt-4 {{ $report->status === 'Acknowledged' ? '' : 'hidden' }}">@csrf @method('PATCH')<button class="rounded-lg border border-green-300 bg-green-50 px-3 py-2 text-xs font-semibold text-green-800 hover:bg-green-100">Mark as resolved</button></form>
                    @endif
                </article>
            @endforeach
        </div>
    @endif

    @if($hasOpenEmergency)
        <section data-emergency-actions class="mt-5 rounded-xl border border-red-200 bg-white p-4" aria-label="Emergency actions">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div><h3 class="font-semibold text-slate-900">Emergency actions remain available</h3><p class="mt-1 text-xs leading-5 text-slate-600">An emergency report is active for this trip. You do not need to submit another report to call for help.</p></div>
                <a href="tel:999" class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-700"><x-icons.lucide name="phone" class="h-4 w-4" />Call 999</a>
            </div>
            @if($contacts->isNotEmpty())
                <div class="mt-4 flex flex-wrap gap-2">@foreach($contacts as $contact)<a href="tel:{{ $contact->phone_number }}" class="inline-flex items-center gap-1.5 rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm font-semibold text-green-800 hover:bg-green-100"><x-icons.lucide name="phone" class="h-3.5 w-3.5" />{{ $contact->name }}</a>@endforeach</div>
            @endif
            @if($isDriver && ! $hasPickedUpPassenger)
                <div class="mt-4 border-t border-red-100 pt-4"><x-trip-confirmation name="emergency-cancel-{{ $trip->trip_id }}" title="End trip early due to emergency?" message="This cancels the trip before any passenger has boarded and notifies all accepted passengers. This cannot be undone." confirm-label="End trip early" :action="route('driver.trips.emergency-cancel', $trip)" variant="danger" class="rounded-lg px-3 py-2 text-sm font-semibold">End trip early</x-trip-confirmation></div>
            @endif
        </section>
    @endif

    <div x-cloak x-show="open" class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="emergency-modal-title">
        <div class="absolute inset-0 bg-slate-900/50" @click="if (!submitting) open = false"></div>

        <div class="relative w-full max-w-lg rounded-2xl bg-white p-5 shadow-xl sm:p-6">
            <button type="button" @click="if (!submitting) open = false" :disabled="submitting" class="absolute right-4 top-4 rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2E7D32]" aria-label="Close emergency report">
                <x-icons.lucide name="x" class="h-5 w-5" />
            </button>

            <div x-show="!reported">
                <div class="pr-10">
                    <p class="flex items-center gap-2 text-sm font-semibold text-red-700"><x-icons.lucide name="triangle-alert" class="h-4 w-4" />Safety assistance</p>
                    <h2 id="emergency-modal-title" class="mt-1 text-xl font-bold text-gray-900">Report Emergency</h2>
                    <p class="mt-2 text-sm leading-6 text-gray-600">Report what happened and we will notify the other trip participants.</p>
                </div>

                <form data-emergency-report-form method="POST" action="{{ route('trips.emergencies.store', $trip) }}" class="mt-6 space-y-5" @submit="submitting = true; errorMessage = ''">
                    @csrf
                    <div>
                        <label for="emergency-type-{{ $trip->trip_id }}" class="mb-1.5 block text-sm font-semibold text-gray-800">Emergency type</label>
                        <select id="emergency-type-{{ $trip->trip_id }}" name="issue_type" required class="block w-full rounded-xl border-gray-300 bg-white text-sm text-gray-800 shadow-sm focus:border-red-500 focus:ring-red-500">
                            <option value="" selected disabled>Select emergency type</option>
                            <option value="medical_emergency">Medical emergency</option>
                            <option value="safety_risk">Immediate safety risk</option>
                            <option value="accident_road_danger">Accident or road danger</option>
                            <option value="other_emergency">Other emergency</option>
                        </select>
                    </div>
                    <div>
                        <label for="emergency-description-{{ $trip->trip_id }}" class="mb-1.5 block text-sm font-semibold text-gray-800">Additional details <span class="font-normal text-gray-500">(optional)</span></label>
                        <textarea id="emergency-description-{{ $trip->trip_id }}" name="description" rows="3" maxlength="1000" placeholder="Describe what happened..." class="block w-full rounded-xl border-gray-300 text-sm text-gray-800 shadow-sm placeholder:text-gray-400 focus:border-red-500 focus:ring-red-500"></textarea>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <p class="flex items-center gap-2 text-sm font-semibold text-gray-800"><x-icons.lucide name="map-pin" class="h-4 w-4 text-[#2E7D32]" />Current location</p>
                        <p class="mt-1 text-sm leading-5 text-gray-600">Your current location will be included in the emergency notification when available.</p>
                    </div>
                    <input type="hidden" name="latitude">
                    <input type="hidden" name="longitude">
                    <input type="hidden" name="location_source" value="unavailable">
                    <div x-show="errorMessage" x-cloak role="alert" class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800"><p class="flex items-start gap-2"><x-icons.lucide name="circle-alert" class="mt-0.5 h-4 w-4 shrink-0" /><span x-text="errorMessage"></span></p></div>
                    <div x-show="submitting" x-cloak class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700" role="status" aria-live="polite"><p class="flex items-center gap-2"><x-icons.lucide name="loader-circle" class="h-4 w-4 animate-spin text-[#2E7D32]" />Reporting emergency… Please wait.</p></div>
                    <p class="text-xs leading-5 text-gray-500">Report this only if you need help. Available trip and location information will be shared with relevant trip participants.</p>
                    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <button type="button" @click="open = false" :disabled="submitting" class="rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60">Cancel</button>
                        <button type="submit" :disabled="submitting" class="inline-flex items-center justify-center gap-2 rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-red-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:bg-red-400"><x-icons.lucide name="triangle-alert" class="h-4 w-4" /><span x-text="submitting ? 'Reporting emergency…' : 'Report Emergency'"></span></button>
                    </div>
                </form>
            </div>

            <div x-show="reported" x-cloak>
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-green-100 text-green-700"><x-icons.lucide name="check" class="h-5 w-5" /></div>
                    <div><h2 id="emergency-modal-title" class="text-xl font-bold text-gray-900">Emergency Reported</h2><p class="mt-1 text-sm leading-6 text-gray-600">Your emergency report has been recorded and relevant trip participants have been notified.</p></div>
                </div>
                <div class="mt-5 space-y-3">
                    <a href="tel:999" class="flex items-center justify-center gap-2 rounded-xl bg-red-600 px-4 py-3 text-sm font-semibold text-white hover:bg-red-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2"><x-icons.lucide name="phone" class="h-4 w-4" />Call 999</a>
                    <div class="rounded-xl border border-gray-200 p-4">
                        <p class="text-sm font-semibold text-gray-900">Call emergency contact</p>
                        @forelse($contacts as $contact)
                            <a href="tel:{{ $contact->phone_number }}" class="mt-2 inline-flex items-center gap-1 text-sm font-semibold text-[#2E7D32] underline decoration-green-300 underline-offset-4 hover:text-[#256b29]"><x-icons.lucide name="phone" class="h-3.5 w-3.5" />{{ $contact->name }}</a>
                        @empty
                            <p class="mt-2 text-sm leading-5 text-gray-600">No emergency contacts are currently configured.</p>
                            <a href="{{ $emergencyContactsRoute }}" class="mt-3 inline-flex items-center gap-1.5 text-sm font-semibold text-[#2E7D32] hover:text-[#256b29]"><x-icons.lucide name="settings" class="h-4 w-4" />Manage emergency contacts</a>
                        @endforelse
                    </div>
                    <button type="button" @click="open = false" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2E7D32] focus-visible:ring-offset-2">Close</button>
                </div>
            </div>
        </div>
    </div>
</section>
