<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Register - GreenPool</title>
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
                <h2 class="text-3xl font-extrabold text-gray-900 mb-2">Create your account</h2>
                <p class="text-gray-500 mb-8">Join GreenPool and start sharing more sustainable journeys.</p>

                <form method="POST" action="{{ route('register') }}">
                    @csrf
                    <div class="mb-4">
                        <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
                        <input id="name" class="block mt-1 w-full border border-gray-300 rounded-md p-2.5 focus:border-green-600 focus:ring-green-600" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name" placeholder="Jackson" />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div class="mb-6">
                        <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                        <input id="email" class="block mt-1 w-full border border-gray-300 rounded-md p-2.5 focus:border-green-600 focus:ring-green-600" type="email" name="email" value="{{ old('email') }}" required autocomplete="username" placeholder="jackson123@gmail.com" />
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>

                    <div class="mb-6">
                        <span class="block text-sm font-medium text-gray-700 mb-2">I want to join as</span>
                        <div class="grid grid-cols-2 gap-4">
                            <label class="relative flex cursor-pointer rounded-lg border border-gray-200 bg-white p-4 shadow-sm hover:border-gray-300 focus:outline-none">
                                <input type="radio" name="role" value="driver" class="peer sr-only" {{ old('role') == 'driver' ? 'checked' : '' }} />
                                <div class="peer-checked:border-[#2E7D32] peer-checked:ring-1 peer-checked:ring-[#2E7D32] peer-checked:bg-green-50/30 rounded-lg border-2 border-transparent absolute inset-0"></div>
                                <div class="relative flex flex-col">
                                    <div class="absolute top-0 right-0 hidden peer-checked:block text-green-600">
                                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <span class="text-2xl mb-2">🚗</span>
                                    <span class="block text-sm font-bold text-gray-900">Driver</span>
                                    <span class="block text-xs text-gray-500 mt-1">Offer rides and share available seats.</span>
                                </div>
                            </label>

                            <label class="relative flex cursor-pointer rounded-lg border border-gray-200 bg-white p-4 shadow-sm hover:border-gray-300 focus:outline-none">
                                <input type="radio" name="role" value="passenger" class="peer sr-only" {{ old('role', 'passenger') == 'passenger' ? 'checked' : '' }} />
                                <div class="peer-checked:border-[#2E7D32] peer-checked:ring-1 peer-checked:ring-[#2E7D32] peer-checked:bg-green-50/30 rounded-lg border-2 border-transparent absolute inset-0"></div>
                                <div class="relative flex flex-col">
                                    <div class="absolute top-0 right-0 hidden peer-checked:block text-green-600">
                                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <span class="text-2xl mb-2">🚶</span>
                                    <span class="block text-sm font-bold text-gray-900">Passenger</span>
                                    <span class="block text-xs text-gray-500 mt-1">Find available rides and travel together.</span>
                                </div>
                            </label>
                        </div>
                        <x-input-error :messages="$errors->get('role')" class="mt-2" />
                    </div>

                    <div class="mb-4">
                        <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                        <div class="relative" x-data="{ showPassword: false }">
                            <input id="password" class="block mt-1 w-full border border-gray-300 rounded-md p-2.5 focus:border-green-600 focus:ring-green-600" :type="showPassword ? 'text' : 'password'" name="password" required autocomplete="new-password" placeholder="••••••••" />
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
                        <p class="text-xs text-gray-400 mt-1">Use at least 8 characters.</p>
                        <x-input-error :messages="$errors->get('password')" class="mt-2" />
                    </div>

                    <div class="mb-6">
                        <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Confirm Password</label>
                        <div class="relative" x-data="{ showConfirmPassword: false }">
                            <input id="password_confirmation" class="block mt-1 w-full border border-gray-300 rounded-md p-2.5 focus:border-green-600 focus:ring-green-600" :type="showConfirmPassword ? 'text' : 'password'" name="password_confirmation" required autocomplete="new-password" placeholder="••••••••" />
                            <div @click="showConfirmPassword = !showConfirmPassword" class="absolute inset-y-0 right-0 pr-3 flex items-center cursor-pointer">
                                <svg x-show="!showConfirmPassword" class="h-5 w-5 text-gray-400 hover:text-gray-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path>
                                </svg>
                                <svg x-show="showConfirmPassword" x-cloak class="h-5 w-5 text-gray-400 hover:text-gray-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                            </div>
                        </div>
                        <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
                    </div>

                    <div class="mt-6 mb-4">
                        <button type="submit" class="w-full bg-[#2E7D32] hover:bg-green-800 text-white font-bold py-3 px-4 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-600 transition ease-in-out duration-150">
                            Create Account
                        </button>
                    </div>

                    <div class="text-center">
                        <span class="text-sm text-gray-500">Already have an account? </span>
                        <a class="text-sm font-bold text-[#2E7D32] hover:text-green-800" href="{{ route('login') }}">
                            Sign in
                        </a>
                    </div>
                </form>
            </div>

        </div>
    </div>
</body>
</html>