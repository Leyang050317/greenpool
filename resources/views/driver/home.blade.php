<x-app-layout>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <h1 class="text-3xl font-bold">
                Driver Home
            </h1>

            <p class="mt-2 text-gray-600">
                Welcome, {{ Auth::user()->name }}
            </p>

        </div>
    </div>
</x-app-layout>