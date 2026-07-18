<x-guest-layout>
    <div class="text-center">
        <h2 class="text-2xl font-bold text-green-600">
            ✅ Email Verified Successfully!
        </h2>

        <p class="mt-4 text-gray-600">
            Your email has been verified successfully.
            You can now login to your account.
        </p>

        <div class="mt-6">
            <a href="{{ route('login') }}"
               class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700">
                Go to Login
            </a>
        </div>
    </div>
</x-guest-layout>