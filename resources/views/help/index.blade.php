<x-support.shell page-title="Help & Support">
    <div class="min-h-screen bg-[#F8FAFC] px-4 py-8 sm:px-6 lg:px-8"><div class="mx-auto max-w-4xl">
        <div class="rounded-2xl bg-[#1b843e] px-6 py-7 text-white sm:px-8"><h1 class="text-2xl font-bold">How can we help?</h1><p class="mt-2 max-w-2xl text-sm leading-6 text-green-50">Find quick guidance for using GreenPool, or open the Help Bot for an answer from our approved FAQ content.</p><button type="button" @click="window.dispatchEvent(new CustomEvent('open-faq-bot'))" class="mt-5 inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-[#1b843e] hover:bg-green-50"><x-icons.lucide name="message-square" class="h-4 w-4" /> Ask GreenPool Help Bot</button></div>
        <section class="mt-6"><h2 class="text-lg font-bold text-slate-900">Quick guides</h2><div class="mt-3 grid gap-3 sm:grid-cols-2">@foreach ([
            ['Find or create a trip', Auth::user()->role === 'driver' ? route('driver.trips.create') : route('passenger.booking'), 'route'],
            ['Manage bookings', Auth::user()->role === 'driver' ? route('driver.booking-requests.index') : route('passenger.bookings.history'), 'clipboard-list'],
            ['Messages and notifications', route('notifications.index'), 'bell'],
            ['Explore tourist attractions', route('attractions.index'), 'map-pinned'],
        ] as [$label, $href, $icon])
            <a href="{{ $href }}" class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 text-sm font-semibold text-slate-800 shadow-sm transition hover:border-green-200 hover:bg-green-50"><span class="flex h-10 w-10 items-center justify-center rounded-xl bg-green-50 text-[#2E7D32]"><x-icons.lucide :name="$icon" class="h-5 w-5" /></span><span class="flex-1">{{ $label }}</span><x-icons.lucide name="arrow-right" class="h-4 w-4 text-slate-400" /></a>
        @endforeach</div></section>
        <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"><h2 class="text-lg font-bold text-slate-900">Frequently asked questions</h2><div class="mt-4 space-y-3 text-sm">@foreach ([
            ['How do I find or create a ride?', 'Passengers can use Find a Ride. Drivers can create a trip from My Trips after their profile and vehicle are ready.'],
            ['Where can I see a booking update?', 'Open Notifications for updates, then go to My Bookings or Booking Requests for the full details.'],
            ['How do I save an attraction?', 'Open Tourist Attractions, select an attraction, then choose Save to Favourites.'],
        ] as [$question, $answer])
            <details class="rounded-xl border border-slate-200 px-4 py-3"><summary class="cursor-pointer font-semibold text-slate-800">{{ $question }}</summary><p class="mt-3 leading-6 text-slate-600">{{ $answer }}</p></details>
        @endforeach</div></section>
        <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6"><h2 class="text-lg font-bold text-slate-900">Need more help?</h2><p class="mt-1 text-sm text-slate-500">Describe the issue and attach screenshots when contacting your GreenPool project support team.</p><a href="mailto:greenpool.support@example.com?subject=GreenPool%20support%20request" class="mt-4 inline-flex items-center gap-2 rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"><x-icons.lucide name="mail" class="h-4 w-4" /> Contact support</a></section>
    </div></div>
</x-support.shell>
