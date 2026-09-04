<x-guest-layout>
    <div class="sm:p-4 text-center">
        {{-- Mail icon --}}
        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-green-50 mb-5">
            <svg class="h-8 w-8 text-[#2E7D32]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"></path>
            </svg>
        </div>

        {{-- Headline --}}
        <h2 class="text-2xl font-bold text-gray-900 mb-3">Check your inbox</h2>

        {{-- Description --}}
        <p class="text-sm leading-6 text-gray-500 mb-6">
            We sent a verification link to
            <span class="block font-semibold text-gray-700 mt-1">{{ auth()->user()->email }}</span>
        </p>

        {{-- Success status message --}}
        @if (session('status') == 'verification-link-sent')
            <div class="mb-6 flex items-center gap-3 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-left">
                <svg class="h-5 w-5 flex-shrink-0 text-[#2E7D32]" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <p class="text-sm font-medium text-green-700">A new verification link has been sent to your email address.</p>
            </div>
        @endif

        @if (session('error'))
            <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-left text-sm font-medium text-red-700">
                {{ session('error') }}
            </div>
        @endif

        {{-- Resend button --}}
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="w-full inline-flex items-center justify-center rounded-lg bg-[#2E7D32] px-6 py-3 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-green-800 focus:outline-none focus:ring-2 focus:ring-[#2E7D32] focus:ring-offset-2">
                Resend Verification Email
            </button>
        </form>

        {{-- Sign out link --}}
        <form method="POST" action="{{ route('logout') }}" class="mt-5">
            @csrf
            <button type="submit" class="text-sm text-gray-500 underline decoration-gray-300 underline-offset-4 transition-colors hover:text-gray-900 hover:decoration-gray-500">
                Sign out
            </button>
        </form>
    </div>
</x-guest-layout>
