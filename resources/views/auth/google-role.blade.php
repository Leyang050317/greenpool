<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Complete Registration - GreenPool</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased text-gray-900 bg-gray-50 flex items-center justify-center min-h-screen">
    <div class="max-w-md w-full bg-white p-8 rounded-xl shadow-md border border-gray-100 m-4">
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-green-100 mb-4">
                <svg class="w-6 h-6 text-[#2E7D32]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            </div>
            <h2 class="text-2xl font-bold text-gray-900">Almost there!</h2>
            <p class="text-sm text-gray-500 mt-2">Please select your role to complete your GreenPool registration.</p>
        </div>

        <form method="POST" action="{{ route('auth.google.storeRole') }}">
            @csrf
            
            <div class="mb-8 space-y-4">
                <label class="flex items-start p-4 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                    <div class="flex items-center h-5">
                        <input type="radio" name="role" value="passenger" class="w-4 h-4 text-[#2E7D32] bg-gray-100 border-gray-300 focus:ring-[#2E7D32]" required>
                    </div>
                    <div class="ml-3 text-sm">
                        <span class="block font-bold text-gray-900">Passenger</span>
                        <span class="block text-gray-500 mt-1">I want to find and book eco-friendly rides.</span>
                    </div>
                </label>

                <label class="flex items-start p-4 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
                    <div class="flex items-center h-5">
                        <input type="radio" name="role" value="driver" class="w-4 h-4 text-[#2E7D32] bg-gray-100 border-gray-300 focus:ring-[#2E7D32]" required>
                    </div>
                    <div class="ml-3 text-sm">
                        <span class="block font-bold text-gray-900">Driver</span>
                        <span class="block text-gray-500 mt-1">I want to offer rides and share my journey.</span>
                    </div>
                </label>
            </div>
            
            @error('role')
                <p class="text-sm text-red-600 mb-4 text-center">{{ $message }}</p>
            @enderror

            <button type="submit" class="w-full flex items-center justify-center px-4 py-3 text-sm font-bold text-white bg-[#2E7D32] border border-transparent rounded-lg hover:bg-green-800 transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-600">
                Complete Sign Up
            </button>
        </form>
    </div>
</body>
</html>