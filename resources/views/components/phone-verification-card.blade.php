@props(['user'])

<section
    class="rounded-2xl border border-gray-200 bg-white p-6"
    x-data="{
        showOtpModal: {{ session('status') === 'phone-otp-sent' || $errors->has('otp') ? 'true' : 'false' }},
        showChangePhoneModal: false,
        editingPhone: {{ ! $user->phone_verified_at || $errors->has('phone_number') ? 'true' : 'false' }},
        seconds: 60,
        timer: null,
        init() {
            if (this.showOtpModal) {
                this.startCountdown();
            }
        },
        startCountdown() {
            clearInterval(this.timer);
            this.seconds = 60;
            this.timer = setInterval(() => {
                if (this.seconds > 0) {
                    this.seconds--;
                    return;
                }

                clearInterval(this.timer);
            }, 1000);
        }
    }"
>
    <div class="flex items-start gap-3">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-green-50 text-[#2E7D32]">
            <x-icons.lucide name="smartphone" class="h-5 w-5" />
        </span>
        <div>
            <h3 class="text-lg font-bold text-gray-950">Phone Verification</h3>
            <p class="mt-1 text-sm text-gray-600">Verify your number so trip participants can contact you safely.</p>
        </div>
    </div>

    @if (session('status') === 'phone-verified')
        <div class="mt-5 flex items-center gap-2 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-800">
            <x-icons.lucide name="badge-check" class="h-5 w-5" />
            Your phone number has been verified.
        </div>
    @endif

    <form method="POST" action="{{ route('phone-verification.send') }}" class="mt-5">
        @csrf
        <label for="phone_number" class="mb-1.5 block text-sm font-semibold text-gray-700">Phone Number</label>
        <div class="flex flex-col gap-3 sm:flex-row">
            <div class="relative flex-1">
                <input
                    id="phone_number"
                    name="phone_number"
                    type="tel"
                    inputmode="tel"
                    autocomplete="tel"
                    value="{{ old('phone_number', $user->phone_number) }}"
                    placeholder="+60 12-345 6789"
                    :readonly="{{ $user->phone_verified_at ? '!editingPhone' : 'false' }}"
                    :class="editingPhone ? 'border-gray-300 focus:border-[#2E7D32] focus:ring-[#2E7D32]' : 'cursor-not-allowed border-gray-200 bg-gray-50 text-gray-600'"
                    class="block w-full rounded-lg pr-28 text-sm shadow-sm"
                    required
                >
                @if ($user->phone_verified_at)
                    <span class="absolute inset-y-0 right-3 inline-flex items-center gap-1 text-xs font-semibold text-green-700">
                        <x-icons.lucide name="shield-check" class="h-4 w-4" />
                        Verified
                    </span>
                @endif
            </div>
            @if ($user->phone_verified_at)
                <button type="button" x-show="!editingPhone" @click="showChangePhoneModal = true" class="inline-flex min-h-10 items-center justify-center rounded-lg bg-[#2E7D32] px-4 py-2 text-sm font-semibold text-white hover:bg-[#256b29]">Change Number</button>
                <button type="submit" x-cloak x-show="editingPhone" class="inline-flex min-h-10 items-center justify-center rounded-lg bg-[#2E7D32] px-4 py-2 text-sm font-semibold text-white hover:bg-[#256b29]">Verify New Number</button>
                <input x-cloak x-show="editingPhone" type="hidden" name="confirm_phone_change" value="1">
            @else
                <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-lg bg-[#2E7D32] px-4 py-2 text-sm font-semibold text-white hover:bg-[#256b29]">Verify Number</button>
            @endif
        </div>
        <p class="mt-2 text-xs text-gray-500">{{ $user->phone_verified_at ? 'Your verified number is locked until you confirm a change.' : 'Verification is required before the number can be used for trip communication.' }}</p>
        @if (!$user->telegram_chat_id)
            <p class="mt-2 text-xs font-medium text-amber-700">Link Telegram first so GreenPool can deliver your verification code.</p>
        @endif
        <x-input-error :messages="$errors->get('phone_number')" class="mt-2" />
    </form>

    @if ($user->phone_verified_at)
        <div x-cloak x-show="showChangePhoneModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" x-transition.opacity>
            <div @click.away="showChangePhoneModal = false" class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                <div class="flex items-start gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-700"><x-icons.lucide name="circle-alert" class="h-5 w-5" /></span>
                    <div>
                        <h3 class="text-lg font-bold text-gray-950">Change phone number?</h3>
                        <p class="mt-2 text-sm leading-6 text-gray-600">Your current verified number will no longer be verified. You must complete OTP verification for the new number before it can be used.</p>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" @click="showChangePhoneModal = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="button" @click="showChangePhoneModal = false; editingPhone = true; $nextTick(() => document.getElementById('phone_number').focus())" class="rounded-lg bg-[#2E7D32] px-4 py-2 text-sm font-semibold text-white hover:bg-[#256b29]">Continue</button>
                </div>
            </div>
        </div>
    @endif

    <div x-cloak x-show="showOtpModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" x-transition.opacity>
        <div @click.away="showOtpModal = false" class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-xl font-bold text-gray-950">Verify your phone number</h3>
                    <p class="mt-2 text-sm leading-6 text-gray-600">Enter the six-digit verification code for {{ $user->phone_number }}.</p>
                </div>
                <button type="button" @click="showOtpModal = false" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700" aria-label="Close verification dialog">
                    <x-icons.lucide name="x" class="h-5 w-5" />
                </button>
            </div>

            @if ($user->telegram_chat_id)
                <div class="mt-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm leading-6 text-green-800">
                    A verification code has been sent to your linked Telegram account. Please check your Telegram chat.
                </div>
            @elseif (app()->environment('local'))
                <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-800">
                    No Telegram account linked. Check storage/logs/laravel.log for the verification code.
                </div>
            @endif

            <form method="POST" action="{{ route('phone-verification.verify') }}" class="mt-6">
                @csrf
                <label for="otp" class="mb-1.5 block text-sm font-semibold text-gray-700">Verification Code</label>
                <input id="otp" name="otp" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" class="block w-full rounded-lg border-gray-300 text-center text-xl font-bold tracking-[0.45em] shadow-sm focus:border-[#2E7D32] focus:ring-[#2E7D32]" required autofocus>
                <x-input-error :messages="$errors->get('otp')" class="mt-2" />
                <button type="submit" class="mt-5 inline-flex w-full min-h-11 items-center justify-center rounded-lg bg-[#2E7D32] px-4 py-2 text-sm font-semibold text-white hover:bg-[#256b29]">
                    Confirm Verification
                </button>
            </form>

            <form method="POST" action="{{ route('phone-verification.send') }}" class="mt-4 text-center">
                @csrf
                <input type="hidden" name="phone_number" value="{{ $user->phone_number }}">
                <button type="submit" :disabled="seconds > 0" class="text-sm font-semibold text-[#2E7D32] disabled:cursor-not-allowed disabled:text-gray-400">
                    <span x-show="seconds > 0" x-text="'Resend in ' + seconds + 's'"></span>
                    <span x-show="seconds === 0">Resend Code</span>
                </button>
            </form>
        </div>
    </div>
</section>
