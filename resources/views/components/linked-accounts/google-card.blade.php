<section class="p-0">
    <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-4">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-gray-200 bg-white">
                <svg viewBox="0 0 24 24" class="h-6 w-6" aria-hidden="true">
                    <path fill="#4285F4" d="M21.6 12.23c0-.71-.06-1.22-.2-1.76H12v3.42h5.52c-.11.85-.71 2.13-2.04 2.99l-.02.11 2.96 2.29.2.02c1.84-1.69 2.98-4.19 2.98-7.07Z"/>
                    <path fill="#34A853" d="M12 22c2.7 0 4.96-.89 6.62-2.42l-3.15-2.45c-.84.58-1.97.98-3.47.98a5.99 5.99 0 0 1-5.66-4.14l-.1.01-3.08 2.38-.03.1A10 10 0 0 0 12 22Z"/>
                    <path fill="#FBBC05" d="M6.34 13.97A6.04 6.04 0 0 1 6 12c0-.69.12-1.36.33-1.97v-.12L3.22 7.5l-.1.05A10 10 0 0 0 2 12c0 1.61.39 3.14 1.12 4.45l3.22-2.48Z"/>
                    <path fill="#EA4335" d="M12 5.89c1.89 0 3.16.82 3.89 1.5l2.84-2.77C16.95 2.96 14.7 2 12 2a10 10 0 0 0-8.88 5.55l3.22 2.48A5.99 5.99 0 0 1 12 5.89Z"/>
                </svg>
            </span>
            <div>
                <h3 class="text-lg font-bold text-gray-950">Google</h3>
                @if ($user->google_id)
                    <p class="mt-1 inline-flex items-center gap-1.5 text-sm font-semibold text-green-700"><x-icons.lucide name="shield-check" class="h-4 w-4" />Connected</p>
                @else
                    <p class="mt-1 text-sm text-gray-500">Not connected</p>
                @endif
            </div>
        </div>

        @if (! $user->google_id)
            <a href="{{ route('linked-accounts.google.redirect') }}" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2E7D32]">
                Connect Google
            </a>
        @elseif ($user->usesGoogleAuthentication())
            <div class="text-sm text-amber-700 sm:text-right"><p class="font-semibold">Google is your primary sign-in method.</p><p class="mt-1 text-xs text-amber-600">Set a password before disconnecting.</p></div>
        @else
            <button type="button" @click="showDisconnectGoogleModal = true" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-red-200 px-4 py-2 text-sm font-semibold text-red-600 transition hover:bg-red-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500">
                Disconnect
            </button>
        @endif
    </div>

    @if (session('error'))
        <p class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-medium text-amber-800">{{ session('error') }}</p>
    @endif

    <div x-cloak x-show="showDisconnectGoogleModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" x-transition.opacity>
        <div @click.away="showDisconnectGoogleModal = false" class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
            <h3 class="text-lg font-bold text-gray-950">Disconnect Google?</h3>
            <p class="mt-2 text-sm leading-6 text-gray-600">You will no longer be able to sign in with this Google account. Your password sign-in will remain available.</p>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" @click="showDisconnectGoogleModal = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancel</button>
                <form method="POST" action="{{ route('linked-accounts.google.destroy') }}">@csrf @method('DELETE')<button type="submit" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">Disconnect</button></form>
            </div>
        </div>
    </div>
</section>
