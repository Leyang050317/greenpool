<x-support.shell page-title="Settings">
    <div class="min-h-screen bg-[#F8FAFC] px-4 py-8 sm:px-6 lg:px-8"><div class="mx-auto max-w-4xl">
        <div><h1 class="text-2xl font-bold text-slate-900">Settings</h1><p class="mt-1 text-sm text-slate-500">Manage how GreenPool keeps you informed and access your account settings.</p></div>
        @if(session('success'))<div class="mt-5 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">{{ session('success') }}</div>@endif
        <form method="POST" action="{{ route('settings.notifications.update') }}" class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">@csrf @method('PUT')
            <div class="flex items-start gap-3"><span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-green-50 text-[#2E7D32]"><x-icons.lucide name="bell" class="h-5 w-5" /></span><div><h2 class="text-lg font-bold text-slate-900">Notification preferences</h2><p class="mt-1 text-sm text-slate-500">Choose the updates you want to receive inside GreenPool.</p><p class="mt-1 text-xs text-slate-400">Turning a category off stops both pop-up alerts and new entries in Notifications. Essential safety and account alerts remain enabled.</p></div></div>
            @php
                $notificationOptions = Auth::user()->role === 'driver' ? [
                    'trip_updates' => ['Driving trip updates', 'Schedule changes, reminders, starts, completions, and cancellations for trips you drive.'],
                    'booking_updates' => ['Booking request alerts', 'New passenger booking requests and cancellations of pending requests.'],
                    'payment_updates' => ['Payment received updates', 'Passenger payment selections and payments you receive.'],
                    'message_alerts' => ['Message alerts', 'Let you know when another GreenPool user sends a message.'],
                    'rating_reminders' => ['Rating updates', 'Ratings received and reminders to rate passengers after a completed trip.'],
                ] : [
                    'trip_updates' => ['Booked trip updates', 'Driver changes, reminders, starts, completions, and cancellations for your trips.'],
                    'booking_updates' => ['Booking status updates', 'Updates when a driver accepts or rejects your booking request.'],
                    'payment_updates' => ['Payment status updates', 'Payment due and completed updates for your bookings.'],
                    'message_alerts' => ['Message alerts', 'Let you know when another GreenPool user sends a message.'],
                    'rating_reminders' => ['Rating updates', 'Ratings received and reminders to rate drivers after a completed trip.'],
                ];
            @endphp
            <div class="mt-5 divide-y divide-slate-100">@foreach ($notificationOptions as $field => [$label, $description])
                <label class="flex cursor-pointer items-center justify-between gap-5 py-4 first:pt-0 last:pb-0"><span><span class="block text-sm font-semibold text-slate-800">{{ $label }}</span><span class="mt-1 block text-sm text-slate-500">{{ $description }}</span></span><span class="relative inline-flex h-6 w-11 shrink-0 items-center"><input type="hidden" name="{{ $field }}" value="0"><input type="checkbox" name="{{ $field }}" value="1" class="peer sr-only" @checked(old($field, $preferences->$field))><span class="h-6 w-11 rounded-full bg-slate-200 transition peer-checked:bg-[#2E7D32]"></span><span class="absolute left-1 h-4 w-4 rounded-full bg-white transition peer-checked:translate-x-5"></span></span></label>
            @endforeach</div>
            <div class="mt-6 flex justify-end"><button type="submit" class="rounded-xl bg-[#2E7D32] px-4 py-2.5 text-sm font-semibold text-white hover:bg-[#256b29]">Save preferences</button></div>
        </form>
        <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"><h2 class="text-lg font-bold text-slate-900">Account settings</h2><p class="mt-1 text-sm text-slate-500">Your profile, password, and account security are managed in Profile.</p><a href="{{ Auth::user()->role === 'driver' ? route('driver.profile.edit') : route('profile.edit') }}" class="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-[#2E7D32] hover:text-[#256b29]">Open Profile <x-icons.lucide name="arrow-right" class="h-4 w-4" /></a></section>
    </div></div>
</x-support.shell>
