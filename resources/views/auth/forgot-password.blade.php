<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Forgot Password - GreenPool</title>

    <!-- Favicon -->
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" href="{{ asset('images/favicon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased text-gray-900 bg-white">
    <div class="flex min-h-screen">
        
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

                <h2 class="text-3xl font-extrabold text-gray-900 mb-2">Forgot password?</h2>
                <p class="text-gray-500 mb-8 text-sm leading-relaxed">
                    No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.
                </p>

                <form method="POST" action="{{ route('password.email') }}">
                    @csrf

                    <div class="mb-6">
                        <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                        <input id="email" class="block mt-1 w-full border border-gray-300 rounded-md p-2.5 focus:border-green-600 focus:ring-green-600" type="email" name="email" :value="old('email')" required autofocus placeholder="mei.ling@example.com" />
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>

                    <div class="mb-6">
                        <button type="submit" class="w-full bg-[#2E7D32] hover:bg-green-800 text-white font-bold py-3 px-4 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-600 transition ease-in-out duration-150">
                            Email Password Reset Link
                        </button>
                    </div>

                    <div class="text-center">
                        <a class="text-sm font-bold text-[#2E7D32] hover:text-green-800" href="{{ route('login') }}">
                            &larr; Back to sign in
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>