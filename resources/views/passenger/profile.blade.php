@extends('passenger.booking.layout')

@section('pageTitle', 'Profile')

@section('content')
    <style>
        [x-cloak] { display: none !important; }
    </style>

    <div class="relative mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8" x-data="{
        currentView: '{{ $errors->has('otp') || $errors->has('phone_number') ? 'main' : ($errors->updatePassword->isNotEmpty() ? 'password' : ($errors->userDeletion->isNotEmpty() ? 'settings' : ($errors->isNotEmpty() ? 'edit' : 'main'))) }}',
        showToast: {{ in_array(session('status'), ['password-updated', 'profile-updated', 'google-account-linked', 'google-account-unlinked'], true) ? 'true' : 'false' }},
        showLogoutModal: false,
        showDisconnectGoogleModal: false,
        showDeleteModal: {{ $errors->userDeletion->isNotEmpty() ? 'true' : 'false' }},
        init() {
            if (this.showToast) {
                setTimeout(() => this.showToast = false, 3000);
            }
        }
    }">
        <div x-show="currentView === 'main'" x-transition.opacity class="max-w-6xl mx-auto">
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-900">My Profile</h1>
                <p class="text-sm text-gray-500 mt-1">View and manage your passenger account information.</p>
            </div>

            <div class="mb-6"><x-profile-completeness-card :profile-completeness="$profileCompleteness" /></div>

            <div class="mt-6 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div>
                <div class="flex flex-col gap-5 border-b border-gray-100 p-6 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex min-w-0 items-center gap-4">
                    @if($user->photo)
                        <img src="{{ asset('storage/'.$user->photo) }}" alt="Profile" class="h-20 w-20 rounded-full border border-gray-200 object-cover">
                    @else
                        <div class="flex h-20 w-20 shrink-0 items-center justify-center rounded-full bg-[#2E7D32] text-2xl font-bold text-white">
                            {{ mb_strtoupper(mb_substr($user->name ?? 'U', 0, 1)) }}
                        </div>
                    @endif
                    <div class="min-w-0">
                        <h3 class="truncate text-xl font-bold text-gray-950">{{ $user->name }}</h3>
                        <p class="mt-1 truncate text-sm text-gray-500">{{ $user->email }}</p>
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                        <span class="rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-[#2E7D32]">{{ ucfirst($user->role) }}</span>
                        <a href="{{ route('ratings.received', $user) }}" class="inline-flex items-center gap-1 text-xs font-semibold text-amber-600 hover:text-amber-700">
                            <x-icons.lucide name="star" class="h-3.5 w-3.5 fill-current" />
                            {{ $user->ratings_received_count > 0 ? number_format((float) $user->ratings_received_avg_score, 1) : 'No rating' }}
                            <span class="font-normal text-gray-400">({{ $user->ratings_received_count }} {{ Str::plural('review', $user->ratings_received_count) }})</span>
                        </a>
                        </div>
                    </div>
                </div>
                <button @click="currentView = 'edit'" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                    <x-icons.lucide name="pencil" class="h-4 w-4" />Edit Profile
                </button>
                </div>
                <div class="grid divide-y divide-gray-100 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                    <div class="p-5"><p class="text-xs font-medium uppercase tracking-wide text-gray-400">Name</p><p class="mt-2 text-sm font-semibold text-gray-900">{{ $user->name }}</p></div>
                    <div class="p-5"><p class="text-xs font-medium uppercase tracking-wide text-gray-400">Email Address</p><p class="mt-2 truncate text-sm font-semibold text-gray-900">{{ $user->email }}</p></div>
                    <div class="p-5"><p class="text-xs font-medium uppercase tracking-wide text-gray-400">Account Type</p><p class="mt-2 text-sm font-semibold text-gray-900">{{ $user->usesGoogleAuthentication() ? 'Google account' : 'Password account' }}</p></div>
                </div>
            </div>

            <div class="border-t border-gray-100 px-6 py-6">
                <x-phone-verification-card :user="$user" />
            </div>

            <div class="border-t border-gray-100 px-6 py-6">
                <x-emergency-contacts-card :user="$user" />
            </div>

            <section class="border-t border-gray-100 px-6 pb-6 pt-8">
            <h4 class="mb-3 text-sm font-bold text-gray-700">Account</h4>
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm divide-y divide-gray-100">
                @if ($canManagePassword)
                <button type="button" @click="currentView = 'password'" class="w-full flex items-center justify-between px-6 py-4 hover:bg-gray-50 transition-colors rounded-t-xl group text-left">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-gray-400 mr-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                        <div>
                            <p class="text-sm font-medium text-gray-900">Change Password</p>
                            <p class="text-xs text-gray-400 mt-0.5">Update your account password</p>
                        </div>
                    </div>
                    <svg class="w-5 h-5 text-gray-300 group-hover:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </button>
                @endif

                <button type="button" @click="currentView = 'settings'" class="w-full flex items-center justify-between px-6 py-4 hover:bg-gray-50 transition-colors group text-left">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-gray-400 mr-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                        <div>
                            <p class="text-sm font-medium text-gray-900">Account Settings</p>
                            <p class="text-xs text-gray-400 mt-0.5">Manage account data</p>
                        </div>
                    </div>
                    <svg class="w-5 h-5 text-gray-300 group-hover:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </button>

                <button type="button" @click="showLogoutModal = true" class="w-full flex items-center justify-between px-6 py-4 hover:bg-gray-50 transition-colors rounded-b-xl group text-left">
                    <div class="flex items-center">
                        <x-icons.lucide name="log-out" class="mr-4 h-5 w-5 shrink-0 text-gray-400" />
                        <p class="text-sm font-medium text-gray-900">Log Out</p>
                    </div>
                    <svg class="w-5 h-5 text-gray-300 group-hover:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </button>
            </div>
            </section>
            </div>
        </div>

        <div x-show="currentView === 'edit'" x-cloak x-transition.opacity class="max-w-2xl mx-auto">
            <div class="flex items-center mb-6">
                <button type="button" @click="currentView = 'main'" class="text-gray-400 hover:text-gray-600 mr-4">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </button>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Edit Profile</h1>
                    <p class="text-sm text-gray-500 mt-1">Keep your personal information up to date.</p>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8">
                <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data">
                    @csrf
                    @method('patch')

                    <div class="mb-6" x-data="{ photoPreview: null }">
                        <input type="file" class="hidden" x-ref="photo" name="photo" accept="image/*"
                            x-on:change="
                                const reader = new FileReader();
                                reader.onload = (e) => { photoPreview = e.target.result; };
                                reader.readAsDataURL($refs.photo.files[0]);
                            ">

                        <label class="block text-xs font-bold text-gray-400 mb-3">Profile Photo</label>
                        <div class="flex items-center">
                            <div x-show="!photoPreview">
                                @if($user->photo)
                                    <img src="{{ asset('storage/'.$user->photo) }}" alt="Profile" class="w-16 h-16 rounded-full mr-5 object-cover border border-gray-200">
                                @else
                                    <div class="w-16 h-16 rounded-full bg-[#2E7D32] flex items-center justify-center text-white font-bold text-xl mr-5">
                                        {{ strtoupper(substr($user->name ?? 'U', 0, 2)) }}
                                    </div>
                                @endif
                            </div>
                            <div x-show="photoPreview" class="w-16 h-16 rounded-full mr-5 bg-cover bg-center bg-no-repeat border border-gray-200" :style="'background-image: url(\'' + photoPreview + '\');'" x-cloak></div>

                            <button type="button" x-on:click.prevent="$refs.photo.click()" class="flex items-center justify-center px-3 py-1.5 text-sm font-medium text-white bg-[#2E7D32] border border-transparent rounded-lg hover:bg-green-800 transition-colors">
                                Change Photo
                            </button>
                        </div>
                        @error('photo')
                            <p class="text-sm text-red-600 mt-2">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-6">
                        <label for="name" class="block text-xs font-bold text-gray-400 mb-1">Name</label>
                        <input id="name" name="name" type="text" class="block w-full border border-gray-200 rounded-md shadow-sm focus:border-green-500 focus:ring-green-500 sm:text-sm p-2.5 text-gray-900" value="{{ old('name', $user->name) }}" required autocomplete="name" />
                        @error('name')
                            <p class="text-sm text-red-600 mt-2">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-6">
                        <label for="email" class="block text-xs font-bold text-gray-400 mb-1">Email Address</label>
                        <input id="email" name="email" type="email" class="block w-full border border-gray-200 rounded-md shadow-sm bg-gray-100 text-gray-500 cursor-not-allowed sm:text-sm p-2.5" value="{{ old('email', $user->email) }}" readonly autocomplete="username" />
                        <p class="mt-1.5 text-xs text-gray-400">Email address cannot be changed.</p>
                        @error('email')
                            <p class="text-sm text-red-600 mt-2">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-10">
                        <label class="block text-xs font-bold text-gray-400 mb-2">Role</label>
                        <div class="flex items-center">
                            <span class="text-xs font-bold text-[#2E7D32] bg-green-100 px-2 py-1 rounded-md mr-3">{{ ucfirst($user->role) }}</span>
                            <span class="text-xs text-gray-400">Role cannot be changed after registration.</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <button type="button" @click="currentView = 'main'" class="flex items-center justify-center px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="flex items-center justify-center px-4 py-2.5 text-sm font-medium text-white bg-[#2E7D32] border border-transparent rounded-lg hover:bg-green-800 transition-colors">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

        @if ($canManagePassword)
        <div x-show="currentView === 'password'" x-cloak x-transition.opacity class="mx-auto max-w-3xl">
            <section class="overflow-hidden rounded-[28px] border border-green-100 bg-white shadow-sm">
                <div class="bg-gradient-to-r from-[#166534] via-[#2E7D32] to-[#4aa65b] px-6 py-7 text-white sm:px-8"><button type="button" @click="currentView = 'main'" class="mb-6 inline-flex items-center gap-2 rounded-lg px-2 py-1 text-sm font-semibold text-green-50 transition hover:bg-white/15 hover:text-white"><x-icons.lucide name="arrow-left" class="h-4 w-4" />Back to profile</button><div class="flex items-start gap-4"><span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-white/15"><x-icons.lucide name="lock-keyhole" class="h-6 w-6" /></span><div><p class="text-sm font-semibold text-green-100">ACCOUNT SECURITY</p><h1 class="mt-1 text-3xl font-bold tracking-tight">Update password</h1><p class="mt-2 text-sm leading-6 text-green-50">Choose a unique password to keep your GreenPool account protected.</p></div></div></div>
                <div class="grid gap-8 p-6 sm:p-8 lg:grid-cols-[1fr_13rem]"><div><p class="mb-6 text-sm leading-6 text-slate-500">Use at least 8 characters with uppercase, lowercase, a number, and a symbol.</p>
                <form method="POST" action="{{ route('password.update') }}">
                    @csrf
                    @method('put')

                    <div class="mb-6">
                        <label for="current_password" class="block text-xs font-bold text-gray-400 mb-1">Current Password</label>
                        <input id="current_password" name="current_password" type="password" class="block w-full border border-gray-200 rounded-md shadow-sm focus:border-green-500 focus:ring-green-500 sm:text-sm p-2.5 text-gray-900" autocomplete="current-password" />
                        @error('current_password', 'updatePassword')
                            <p class="text-sm text-red-600 mt-2">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-6">
                        <label for="password" class="block text-xs font-bold text-gray-400 mb-1">New Password</label>
                        <input id="password" name="password" type="password" class="block w-full border border-gray-200 rounded-md shadow-sm focus:border-green-500 focus:ring-green-500 sm:text-sm p-2.5 text-gray-900" autocomplete="new-password" />
                        @error('password', 'updatePassword')
                            <p class="text-sm text-red-600 mt-2">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-8">
                        <label for="password_confirmation" class="block text-xs font-bold text-gray-400 mb-1">Confirm Password</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" class="block w-full border border-gray-200 rounded-md shadow-sm focus:border-green-500 focus:ring-green-500 sm:text-sm p-2.5 text-gray-900" autocomplete="new-password" />
                        @error('password_confirmation', 'updatePassword')
                            <p class="text-sm text-red-600 mt-2">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                        <button type="button" @click="currentView = 'main'" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-300 px-5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            Cancel
                        </button>
                        <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-[#2E7D32] px-5 text-sm font-semibold text-white shadow-sm hover:bg-[#256b29]">
                            Save new password
                        </button>
                    </div>
                </form>
                </div><aside class="h-fit rounded-2xl border border-green-100 bg-green-50 p-5"><x-icons.lucide name="shield-check" class="h-6 w-6 text-[#2E7D32]" /><h2 class="mt-3 font-bold text-slate-900">Keep it private</h2><p class="mt-2 text-sm leading-6 text-slate-600">Never share your password or a reset link. GreenPool will never ask for it in chat.</p></aside></div>
            </section>
        </div>
        @endif

        <div x-show="currentView === 'settings'" x-cloak x-transition.opacity class="mx-auto max-w-4xl">
            <div class="mb-6 overflow-hidden rounded-[28px] bg-slate-900 px-6 py-7 text-white shadow-sm sm:px-8"><button type="button" @click="currentView = 'main'" class="mb-6 inline-flex items-center gap-2 rounded-lg px-2 py-1 text-sm font-semibold text-slate-300 transition hover:bg-white/10 hover:text-white"><x-icons.lucide name="arrow-left" class="h-4 w-4" />Back to profile</button><div class="flex items-start gap-4"><span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/10 text-green-300"><x-icons.lucide name="settings" class="h-6 w-6" /></span><div><p class="text-sm font-semibold tracking-wide text-green-300">ACCOUNT CENTRE</p><h1 class="mt-1 text-3xl font-bold tracking-tight">Account settings</h1><p class="mt-2 text-sm leading-6 text-slate-300">Review sign-in details, connected accounts, and account data.</p></div></div></div>

            <div class="overflow-hidden rounded-[24px] border border-slate-200 bg-white shadow-sm">
                <section class="p-6 sm:p-7">
                    <p class="text-xs font-bold uppercase tracking-[0.12em] text-slate-400">Account details</p>
                    <div class="mt-4 grid overflow-hidden rounded-xl border border-slate-200 sm:grid-cols-2">
                        <div class="border-b border-slate-100 p-5 sm:border-b-0 sm:border-r"><p class="text-xs font-bold uppercase tracking-wide text-slate-400">Account email</p><p class="mt-2 break-all text-sm font-semibold text-slate-900">{{ $user->email }}</p><p class="mt-1 text-xs text-slate-500">Email changes are protected for account safety.</p></div>
                        <div class="p-5"><p class="text-xs font-bold uppercase tracking-wide text-slate-400">Account role</p><p class="mt-2 inline-flex rounded-full bg-green-100 px-2.5 py-1 text-sm font-semibold text-[#2E7D32]">{{ ucfirst($user->role) }}</p><p class="mt-1 text-xs text-slate-500">Your role is set when you register.</p></div>
                    </div>
                </section>

                <section class="border-t border-slate-200 p-6 sm:p-7">
                    <p class="mb-4 text-xs font-bold uppercase tracking-[0.12em] text-slate-400">Linked accounts</p>
                    <x-linked-accounts.google-card :user="$user" />
                </section>

                <section class="border-t border-slate-200 p-6 sm:p-7">
                    <p class="mb-2 text-xs font-bold uppercase tracking-[0.12em] text-slate-400">Recent login activity</p>
                    <div class="divide-y divide-gray-100">
                @forelse ($recentLogins as $login)
                    <div class="flex items-center gap-4 border-b border-gray-100 py-4 last:border-b-0">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-green-50 text-[#2E7D32]">
                            <x-icons.lucide :name="str_starts_with($login->user_agent, 'Mozilla/5.0 (iPhone') || str_contains($login->user_agent, 'Android') ? 'smartphone' : 'monitor'" class="h-5 w-5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-gray-900">{{ \App\Support\UserAgentParser::describe($login->user_agent) }}</p>
                            <p class="mt-1 text-xs text-gray-500">{{ $login->ip_address }} · {{ $login->login_at->format('M d, Y - H:i') }}</p>
                        </div>
                    </div>
                @empty
                    <div class="py-6 text-center">
                        <span class="mx-auto flex h-10 w-10 items-center justify-center rounded-lg bg-gray-100 text-gray-400"><x-icons.lucide name="shield-check" class="h-5 w-5" /></span>
                        <p class="mt-3 text-sm font-semibold text-gray-700">No login activity yet</p>
                        <p class="mt-1 text-xs text-gray-500">Your successful sign-ins will appear here.</p>
                    </div>
                @endforelse
                    </div>
                </section>

                <section class="border-t border-red-100 bg-red-50/40 p-6 sm:p-7">
                    <p class="mb-4 text-xs font-bold uppercase tracking-[0.12em] text-red-500">Danger zone</p>
                <div class="flex items-start">
                    <div class="flex-shrink-0 mt-0.5 mr-4 text-red-500">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    </div>
                    <div>
                        <h5 class="text-sm font-bold text-gray-900">Delete Account</h5>
                        <p class="text-sm text-gray-500 mt-1 mb-4">Permanently delete your GreenPool account and associated account data.</p>
                        <button type="button" @click="showDeleteModal = true" class="inline-flex items-center px-4 py-2 border border-red-200 text-sm font-medium rounded-lg text-red-600 bg-white hover:bg-red-50 transition-colors">
                            Delete Account
                        </button>
                    </div>
                </div>
                </section>
            </div>
        </div>

        <div x-show="showToast" x-cloak x-transition.opacity.duration.300ms class="fixed bottom-8 left-1/2 transform -translate-x-1/2 z-40">
            <div class="flex items-center bg-gray-900 text-white px-5 py-3 rounded-xl shadow-lg">
                <span class="text-sm font-medium whitespace-nowrap">{{ match(session('status')) { 'password-updated' => 'Password updated successfully.', 'google-account-linked' => 'Google account connected successfully.', 'google-account-unlinked' => 'Google account disconnected successfully.', default => 'Profile updated successfully.' } }}</span>
                <button @click="showToast = false" class="ml-6 text-gray-400 hover:text-white transition-colors focus:outline-none">X</button>
            </div>
        </div>

        <div x-show="showLogoutModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50" x-transition.opacity>
            <div @click.away="showLogoutModal = false" class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
                <h3 class="text-lg font-bold text-gray-900">Log out?</h3>
                <p class="text-sm text-gray-600 mt-2 mb-6">Are you sure you want to log out of GreenPool?</p>
                <div class="flex items-center space-x-3">
                    <button @click="showLogoutModal = false" class="w-1/2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                    <form method="POST" action="{{ route('logout') }}" class="w-1/2 m-0">
                        @csrf
                        <button type="submit" class="w-full px-4 py-2 text-sm font-medium text-white bg-[#2E7D32] rounded-lg hover:bg-green-800">Log Out</button>
                    </form>
                </div>
            </div>
        </div>

        <div x-show="showDeleteModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50" x-transition.opacity>
            <div @click.away="showDeleteModal = false" class="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
                <h2 class="text-lg font-medium text-gray-900">Are you sure you want to delete your account?</h2>
                <p class="mt-1 text-sm text-gray-600">Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm.</p>

                <form method="POST" action="{{ route('profile.destroy') }}" class="mt-6">
                    @csrf
                    @method('delete')

                    <input id="password" name="password" type="password" class="block w-full border border-gray-200 rounded-md shadow-sm focus:border-red-500 focus:ring-red-500 sm:text-sm p-2.5 text-gray-900" placeholder="Password" required />
                    @error('password', 'userDeletion')
                        <p class="text-sm text-red-600 mt-2">{{ $message }}</p>
                    @enderror

                    <div class="mt-6 flex justify-end space-x-3">
                        <button type="button" @click="showDeleteModal = false" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">Cancel</button>
                        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700">Delete Account</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
