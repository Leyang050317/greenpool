<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reactivate Account - GreenPool</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased">
    <main class="flex min-h-screen items-center justify-center px-5 py-10">
        <section class="w-full max-w-lg overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-xl shadow-slate-200/60">
            <div class="bg-gradient-to-r from-[#166534] via-[#2E7D32] to-[#4aa65b] px-7 py-8 text-white sm:px-9">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/15"><x-icons.lucide name="lock-keyhole" class="h-6 w-6" /></div>
                <p class="mt-6 text-xs font-bold tracking-[0.14em] text-green-100">ACCOUNT REACTIVATION</p>
                <h1 class="mt-2 text-3xl font-bold tracking-tight">Welcome back to GreenPool</h1>
                <p class="mt-3 text-sm leading-6 text-green-50">Your account is currently deactivated. You can reactivate it within 30 days and continue using GreenPool.</p>
            </div>
            <div class="space-y-5 p-7 sm:p-9">
                <div class="rounded-xl border border-amber-100 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900">
                    Your previous trip, payment, and safety records remain protected. Future trips and bookings that were cancelled will not be restored automatically.
                </div>
                <form method="POST" action="{{ route('account.reactivate') }}">
                    @csrf
                    <button type="submit" class="inline-flex min-h-12 w-full items-center justify-center rounded-xl bg-[#2E7D32] px-5 text-sm font-bold text-white transition hover:bg-[#256b29] focus:outline-none focus:ring-2 focus:ring-[#2E7D32] focus:ring-offset-2">
                        Reactivate my account
                    </button>
                </form>
                <form method="POST" action="{{ route('logout') }}" class="text-center">
                    @csrf
                    <button type="submit" class="text-sm font-semibold text-slate-500 hover:text-slate-800">Keep my account deactivated</button>
                </form>
            </div>
        </section>
    </main>
</body>
</html>
