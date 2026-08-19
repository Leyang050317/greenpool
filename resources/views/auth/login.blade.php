<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login - GreenPool</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        [x-cloak] { display: none !important; }

        input[type="password"]::-ms-reveal,
        input[type="password"]::-ms-clear {
            display: none;
        }
    </style>
</head>
<body class="font-sans antialiased text-gray-900 bg-white">
    <div class="flex min-h-screen" x-data="{ showDeactivatedError: false }">
        
        <div class="hidden lg:flex lg:w-1/2 bg-[#2E7D32] relative p-12 text-white">
            
            <div class="absolute top-8 left-8 flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center bg-white/20">
                    <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-1h12v1c0 .55.45 1 1 1h1c.55 0 1-.45 1-1v-8l-2.08-5.99zM6.5 16c-.83 0-1.5-.67-1.5-1.5S5.67 13 6.5 13s1.5.67 1.5 1.5S7.33 16 6.5 16zm11 0c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zM5 11l1.5-4.5h11L19 11H5z"></path>
                    </svg>
                </div>
                <span class="font-semibold text-white text-lg tracking-tight">GreenPool</span>
            </div>

            <div class="flex flex-col w-full max-w-lg mx-auto justify-center">
                
                <div class="mb-12 flex items-center justify-center w-64 h-64 rounded-full bg-white/10 mx-auto">
                    <div class="flex flex-col items-center mt-2">
                        <svg class="w-24 h-24 text-white" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M5 11h14a2 2 0 0 1 2 2v4H3v-4a2 2 0 0 1 2-2Z"></path>
                            <path d="M7 11V7a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v4"></path>
                            <circle cx="7" cy="17" r="2"></circle>
                            <circle cx="17" cy="17" r="2"></circle>
                        </svg>
                        <div class="w-16 h-1.5 bg-white/40 rounded-full mt-2"></div>
                    </div>
                </div>

                <div class="text-left">
                    <h1 class="text-4xl lg:text-5xl font-bold mb-4 leading-tight">
                        Share the journey.<br>
                        Reduce the impact.
                    </h1>
                    <p class="text-green-100 text-lg">
                        Connect with your community and make every journey more sustainable.
                    </p>
                </div>
            </div>
        </div>

        <div class="w-full lg:w-1/2 flex items-center justify-center p-8 bg-white">
            <div class="w-full max-w-md">
                <x-auth-session-status class="mb-4" :status="session('status')" />

                @if (session('error'))
                    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-700">
                        {{ session('error') }}
                    </div>
                @endif

                <h2 class="text-3xl font-extrabold text-gray-900 mb-2">Welcome back</h2>
                <p class="text-gray-500 mb-8">Sign in to continue to GreenPool.</p>

                <div x-show="showDeactivatedError" x-cloak class="mb-6 p-4 bg-orange-50 border border-orange-200 rounded-lg">
                    <p class="text-sm font-medium text-orange-600">
                        Your account has been deactivated. Contact support to reactivate.
                    </p>
                </div>

                <form method="POST" action="{{ route('login') }}">
                    @csrf

                    <div class="mb-6">
                        <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                        <input id="email" class="block mt-1 w-full border border-gray-300 rounded-md p-2.5 focus:border-green-600 focus:ring-green-600" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" placeholder="mei.ling@example.com" />
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>

                    <div class="mb-2">
                        <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                        <div class="relative" x-data="{ showPassword: false }">
                            <input id="password" class="block mt-1 w-full border border-gray-300 rounded-md p-2.5 focus:border-green-600 focus:ring-green-600" :type="showPassword ? 'text' : 'password'" name="password" required autocomplete="current-password" placeholder="••••••••" />
                            
                            <div @click="showPassword = !showPassword" class="absolute inset-y-0 right-0 pr-3 flex items-center cursor-pointer">
                                <svg x-show="!showPassword" class="h-5 w-5 text-gray-400 hover:text-gray-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path>
                                </svg>
                                <svg x-show="showPassword" x-cloak class="h-5 w-5 text-gray-400 hover:text-gray-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                            </div>
                        </div>
                        <x-input-error :messages="$errors->get('password')" class="mt-2" />
                    </div>

                    <div class="flex items-center justify-start mb-6 mt-2">
                        @if (Route::has('password.request'))
                            <a class="text-sm text-[#2E7D32] hover:text-green-800" href="{{ route('password.request') }}">
                                Forgot password?
                            </a>
                        @endif
                    </div>

                    <div class="mb-4">
                        <button type="submit" class="w-full bg-[#2E7D32] hover:bg-green-800 text-white font-bold py-3 px-4 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-600 transition ease-in-out duration-150">
                            Sign In
                        </button>
                    </div>
                </form>

                    <div class="mt-4 flex items-center justify-between">
                <span class="border-b w-1/5 lg:w-1/4"></span>
                <span class="text-xs text-center text-gray-500 uppercase">or login with</span>
                <span class="border-b w-1/5 lg:w-1/4"></span>
            </div>

            <div class="mt-4 mb-6">
                <a href="{{ route('auth.google') }}" class="w-full flex items-center justify-center px-4 py-3 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-600 transition ease-in-out duration-150">
                    <svg class="w-5 h-5 mr-3" viewBox="0 0 24 24">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                    </svg>
                    Sign in with Google
                </a>
            </div>

                    <div class="text-center">
                        <span class="text-sm text-gray-500">Don't have an account? </span>
                        <a class="text-sm font-bold text-[#2E7D32] hover:text-green-800" href="{{ route('register') }}">
                            Create an account
                        </a>
                    </div>
            </div>
        </div>
    </div>
</body>
</html>
