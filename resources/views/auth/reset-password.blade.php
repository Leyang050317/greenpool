<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password - GreenPool</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" href="{{ asset('images/favicon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased">
    <main class="grid min-h-screen lg:grid-cols-[minmax(20rem,0.9fr)_minmax(30rem,1.1fr)]">
        <section class="relative hidden overflow-hidden bg-[#1f7533] p-10 text-white lg:flex lg:flex-col">
            <div class="absolute -right-24 top-24 h-72 w-72 rounded-full bg-green-300/15"></div><div class="absolute -bottom-28 -left-20 h-80 w-80 rounded-full border-[34px] border-white/10"></div>
            <a href="{{ url('/') }}" class="relative inline-flex w-fit items-center gap-3 text-lg font-bold"><span class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/15"><x-icons.lucide name="car-front" class="h-6 w-6" /></span>GreenPool</a>
            <div class="relative my-auto max-w-md"><span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-white/15"><x-icons.lucide name="lock-keyhole" class="h-8 w-8" /></span><p class="mt-8 text-sm font-bold tracking-[0.16em] text-green-200">ACCOUNT RECOVERY</p><h1 class="mt-3 text-4xl font-bold leading-tight">Set a new password with confidence.</h1><p class="mt-5 text-base leading-7 text-green-100">Your reset link is private and expires soon. Choose a strong password you do not use anywhere else.</p></div>
            <p class="relative text-sm text-green-100">Secure access for every shared journey.</p>
        </section>

        <section class="flex items-center justify-center px-5 py-10 sm:p-10">
            <div class="w-full max-w-lg">
                <a href="{{ route('login') }}" class="mb-8 inline-flex items-center gap-2 text-sm font-semibold text-slate-500 transition hover:text-[#2E7D32]"><x-icons.lucide name="arrow-left" class="h-4 w-4" />Back to sign in</a>
                <div class="overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-xl shadow-slate-200/60">
                    <div class="border-b border-slate-100 bg-gradient-to-r from-green-50 to-white px-6 py-7 sm:px-8"><div class="flex items-start gap-4"><span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-[#2E7D32] text-white"><x-icons.lucide name="lock-keyhole" class="h-6 w-6" /></span><div><p class="text-xs font-bold tracking-[0.14em] text-[#2E7D32]">PASSWORD RESET</p><h2 class="mt-1 text-3xl font-bold tracking-tight text-slate-950">Create a new password</h2><p class="mt-2 text-sm leading-6 text-slate-500">Enter a strong new password for your GreenPool account.</p></div></div></div>
                    <form method="POST" action="{{ route('password.store') }}" class="space-y-5 p-6 sm:p-8" x-data="{ showPassword: false, showConfirmation: false }">
                        @csrf
                        <input type="hidden" name="token" value="{{ $request->route('token') }}">
                        <div><label for="email" class="mb-1.5 block text-sm font-semibold text-slate-700">Account email</label><input id="email" class="block w-full rounded-xl border-slate-300 bg-slate-50 px-3 py-2.5 text-sm text-slate-600 shadow-sm focus:border-[#2E7D32] focus:ring-[#2E7D32]" type="email" name="email" value="{{ old('email', $request->email) }}" required autocomplete="username" readonly /><x-input-error :messages="$errors->get('email')" class="mt-2" /></div>
                        <div><label for="password" class="mb-1.5 block text-sm font-semibold text-slate-700">New password</label><div class="relative"><input id="password" class="block w-full rounded-xl border-slate-300 px-3 py-2.5 pr-12 text-sm shadow-sm focus:border-[#2E7D32] focus:ring-[#2E7D32]" :type="showPassword ? 'text' : 'password'" name="password" required autocomplete="new-password" autofocus /><button type="button" @click="showPassword = !showPassword" class="absolute inset-y-0 right-0 px-3 text-slate-400 hover:text-slate-700" :aria-label="showPassword ? 'Hide password' : 'Show password'"><x-icons.lucide name="eye" class="h-5 w-5" /></button></div><p class="mt-2 text-xs leading-5 text-slate-500">Use 8+ characters with uppercase, lowercase, a number, and a symbol.</p><x-input-error :messages="$errors->get('password')" class="mt-2" /></div>
                        <div><label for="password_confirmation" class="mb-1.5 block text-sm font-semibold text-slate-700">Confirm new password</label><div class="relative"><input id="password_confirmation" class="block w-full rounded-xl border-slate-300 px-3 py-2.5 pr-12 text-sm shadow-sm focus:border-[#2E7D32] focus:ring-[#2E7D32]" :type="showConfirmation ? 'text' : 'password'" name="password_confirmation" required autocomplete="new-password" /><button type="button" @click="showConfirmation = !showConfirmation" class="absolute inset-y-0 right-0 px-3 text-slate-400 hover:text-slate-700" :aria-label="showConfirmation ? 'Hide password confirmation' : 'Show password confirmation'"><x-icons.lucide name="eye" class="h-5 w-5" /></button></div><x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" /></div>
                        <div class="rounded-xl border border-amber-100 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900"><span class="font-bold">Security reminder:</span> this reset link expires in 60 minutes. GreenPool will never ask for your password by email or chat.</div>
                        <button type="submit" class="inline-flex min-h-12 w-full items-center justify-center rounded-xl bg-[#2E7D32] px-5 text-sm font-bold text-white shadow-sm transition hover:bg-[#256b29] focus:outline-none focus:ring-2 focus:ring-[#2E7D32] focus:ring-offset-2">Reset password</button>
                    </form>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
