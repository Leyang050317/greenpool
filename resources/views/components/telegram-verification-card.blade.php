@props(['user'])

<section class="rounded-2xl border border-gray-200 bg-white p-6">
    <div class="flex items-start justify-between gap-4">
        <div class="flex items-start gap-3">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-[#2AABEE]/10 text-[#2AABEE]">
                <svg class="h-5 w-5 fill-current" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69a.2.2 0 00-.05-.18c-.06-.05-.14-.03-.21-.02-.09.02-1.49.95-4.22 2.79-.4.27-.76.41-1.08.4-.36-.01-1.04-.2-1.55-.37-.63-.2-1.12-.31-1.08-.66.02-.18.27-.36.75-.55 2.92-1.27 4.86-2.11 5.83-2.51 2.78-1.16 3.35-1.36 3.73-1.36.08 0 .27.02.39.12.1.08.13.19.14.27-.01.06.01.24 0 .38z"/>
                </svg>
            </span>
            <div>
                <h3 class="text-lg font-bold text-gray-950">Telegram Verification</h3>
                <p class="mt-1 text-sm text-gray-600">Link your Telegram account to receive secure OTP verification codes.</p>

                <x-input-error :messages="$errors->get('telegram')" class="mt-3" />

                @if (session('status') === 'telegram-unlinked')
                    <div class="mt-5 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm font-semibold text-blue-800">
                        Your Telegram account has been unlinked.
                    </div>
                @endif
                
                @if ($user->telegram_chat_id)
                    <div class="mt-5 flex items-center gap-2 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-800">
                        <x-icons.lucide name="badge-check" class="h-5 w-5" />
                        Your Telegram account is successfully linked.
                    </div>
                @endif
            </div>
        </div>
        
        @if (!$user->telegram_chat_id)
            <div class="shrink-0 mt-1">
                <x-telegram-link-button />
            </div>
        @else
            <form method="POST" action="{{ route('telegram.unlink') }}" class="mt-1 shrink-0" onsubmit="return confirm('Unlink this Telegram account? You will not be able to receive verification codes until you link it again.')">
                @csrf
                @method('DELETE')
                <button type="submit" class="inline-flex items-center justify-center rounded-lg border border-red-200 px-4 py-2.5 text-sm font-semibold text-red-700 hover:bg-red-50">
                    Unlink
                </button>
            </form>
        @endif
    </div>
</section>
