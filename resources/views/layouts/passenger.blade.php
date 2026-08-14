<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GreenPool</title>

    <!-- 载入CSS -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body
    class="bg-gray-100"
    x-data="{ open: false }"
    data-auth-id="{{ Auth::id() }}"
    data-auth-role="{{ Auth::user()?->role }}"
>

    <!-- resources/views/components/passenger/header.blade.php -->
    <x-passenger.header />

    <div class="flex">

        <x-passenger.sidebar />

        <main class="flex-1 p-6 overflow-y-auto">
            @yield('content')
        </main>

    </div>

</body>

</html>
