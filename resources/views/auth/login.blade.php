<x-guest-layout>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="block mt-1 w-full"
                type="password"
                name="password"
                required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="mt-4 flex items-center justify-between">

            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me"
                    type="checkbox"
                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                    name="remember">

                <span class="ms-2 text-sm text-gray-600">
                    {{ __('Remember me') }}
                </span>
            </label>

            @if (Route::has('password.request'))
            <a href="{{ route('password.request') }}"
                class="text-sm text-gray-600 underline hover:text-gray-900">
                {{ __('Forgot your password?') }}
            </a>
            @endif

        </div>

        <div class="mt-6 relative flex items-center justify-center">

            <x-primary-button>
                {{ __('Log in') }}
            </x-primary-button>

        </div>

        <div class="mt-6 text-center border-t border-gray-200 pt-4">
            <p class="text-sm text-gray-600">
                Don't have an account?

                <a href="{{ route('register') }}"
                    class="font-semibold text-emerald-600 hover:text-emerald-700">
                    Register
                </a>
            </p>
        </div>
    </form>
</x-guest-layout>