<x-app-layout>
    <style>
        [x-cloak] { display: none !important; }
    </style>

    <div class="max-w-7xl mx-auto p-4 sm:p-8 relative" x-data="{ 
        currentView: '{{ $errors->updatePassword->isNotEmpty() ? 'password' : ($errors->userDeletion->isNotEmpty() ? 'settings' : ($errors->isNotEmpty() ? 'edit' : 'main')) }}', 
        showToast: {{ session('status') === 'password-updated' || session('status') === 'profile-updated' ? 'true' : 'false' }}, 
        showLogoutModal: false, 
        showDeactivateModal: false,
        showDeleteModal: {{ $errors->userDeletion->isNotEmpty() ? 'true' : 'false' }},
        init() {
            if (this.showToast) {
                setTimeout(() => this.showToast = false, 3000);
            }
        }
    }">
        
        <!-- MAIN PROFILE VIEW -->
        <div x-show="currentView === 'main'" x-transition.opacity class="max-w-3xl mx-auto">
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-900">My Profile</h1>
                <p class="text-sm text-gray-500 mt-1">View and manage your personal account information.</p>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 flex items-center justify-between mb-6">
                <div class="flex items-center">
                    @if(Auth::user()->photo)
                        <img src="{{ asset('storage/' . Auth::user()->photo) }}" alt="Profile" class="w-16 h-16 rounded-full mr-5 object-cover border border-gray-200">
                    @else
                        <div class="w-16 h-16 rounded-full bg-[#2E7D32] flex items-center justify-center text-white font-bold text-xl mr-5">
                            {{ strtoupper(substr(Auth::user()->name ?? 'U', 0, 2)) }}
                        </div>
                    @endif
                    <div>
                        <h3 class="text-lg font-bold text-gray-900">{{ Auth::user()->name ?? 'Name' }}</h3>
                        <p class="text-sm text-gray-500 mb-1.5">{{ Auth::user()->email ?? 'email@example.com' }}</p>
                        <span class="text-xs font-bold text-[#2E7D32] bg-green-100 px-2 py-1 rounded-md">{{ ucfirst(Auth::user()->role) }}</span>
                        <a href="{{ route('ratings.received', $user) }}" class="mt-2 flex w-fit items-center gap-1 text-sm font-semibold text-amber-500 hover:text-amber-600">
                            <x-icons.lucide name="star" class="h-4 w-4 fill-current" />
                            {{ $user->ratings_received_count > 0 ? number_format((float) $user->ratings_received_avg_score, 1) : 'No rating' }}
                            <span class="font-normal text-gray-400">({{ $user->ratings_received_count }} {{ Str::plural('review', $user->ratings_received_count) }})</span>
                        </a>
                    </div>
                </div>
                <button @click="currentView = 'edit'" class="flex items-center justify-center px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                    <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                    Edit
                </button>
            </div>

            <h4 class="text-sm font-bold text-gray-700 mb-3">Personal Information</h4>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-8">
                <div class="px-6 py-4 border-b border-gray-100">
                    <p class="text-xs text-gray-400 mb-1">Name</p>
                    <p class="text-sm font-medium text-gray-900">{{ Auth::user()->name ?? 'Name' }}</p>
                </div>
                <div class="px-6 py-4 border-b border-gray-100">
                    <p class="text-xs text-gray-400 mb-1">Email Address</p>
                    <p class="text-sm font-medium text-gray-900">{{ Auth::user()->email ?? 'email@example.com' }}</p>
                </div>
                <div class="px-6 py-4">
                    <p class="text-xs text-gray-400 mb-1">Role</p>
                    <p class="text-sm font-medium text-gray-900">{{ ucfirst(Auth::user()->role) }}</p>
                </div>
            </div>

            <h4 class="text-sm font-bold text-gray-700 mb-3">Account</h4>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 divide-y divide-gray-100">
                @if ($canManagePassword)
                <button type="button" @click="currentView = 'password'" class="w-full flex items-center justify-between px-6 py-4 hover:bg-gray-50 transition-colors rounded-t-xl group text-left">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-gray-400 mr-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                        <div>
                            <p class="text-sm font-medium text-gray-900">Change Password</p>
                            <p class="text-xs text-gray-400 mt-0.5">••••••••</p>
                        </div>
                    </div>
                    <svg class="w-5 h-5 text-gray-300 group-hover:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </button>
                @endif
                
                <button type="button" @click="currentView = 'settings'" class="w-full flex items-center justify-between px-6 py-4 hover:bg-gray-50 transition-colors group text-left">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-gray-400 mr-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                        <div>
                            <p class="text-sm font-medium text-gray-900">Account Settings</p>
                            <p class="text-xs text-gray-400 mt-0.5">Manage account preferences and data</p>
                        </div>
                    </div>
                    <svg class="w-5 h-5 text-gray-300 group-hover:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </button>
                
                <button type="button" @click="showLogoutModal = true" class="w-full flex items-center justify-between px-6 py-4 hover:bg-gray-50 transition-colors rounded-b-xl group text-left">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-gray-400 mr-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                        <p class="text-sm font-medium text-gray-900">Log Out</p>
                    </div>
                    <svg class="w-5 h-5 text-gray-300 group-hover:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </button>
            </div>
        </div>

        <!-- EDIT PROFILE VIEW -->
        <div x-show="currentView === 'edit'" x-cloak x-transition.opacity class="max-w-2xl mx-auto">
            <div class="flex items-center mb-6">
                <button type="button" @click="currentView = 'main'" class="text-gray-400 hover:text-gray-600 mr-4">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
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

                    <div class="mb-6" x-data="{ photoName: null, photoPreview: null }">
                        <input type="file" class="hidden" x-ref="photo" name="photo" accept="image/*"
                            x-on:change="
                                photoName = $refs.photo.files[0].name;
                                const reader = new FileReader();
                                reader.onload = (e) => {
                                    photoPreview = e.target.result;
                                };
                                reader.readAsDataURL($refs.photo.files[0]);
                            ">
                        
                        <label class="block text-xs font-bold text-gray-400 mb-3">Profile Photo</label>
                        <div class="flex items-center">
                            <div x-show="!photoPreview">
                                @if(Auth::user()->photo)
                                    <img src="{{ asset('storage/' . Auth::user()->photo) }}" alt="Profile" class="w-16 h-16 rounded-full mr-5 object-cover border border-gray-200">
                                @else
                                    <div class="w-16 h-16 rounded-full bg-[#2E7D32] flex items-center justify-center text-white font-bold text-xl mr-5">
                                        {{ strtoupper(substr(Auth::user()->name ?? 'U', 0, 2)) }}
                                    </div>
                                @endif
                            </div>
                            <div x-show="photoPreview" class="w-16 h-16 rounded-full mr-5 bg-cover bg-center bg-no-repeat border border-gray-200" :style="'background-image: url(\'' + photoPreview + '\');'" x-cloak>
                            </div>

                            <div class="flex flex-col space-y-2">
                                <button type="button" x-on:click.prevent="$refs.photo.click()" class="flex items-center justify-center px-3 py-1.5 text-sm font-medium text-white bg-[#2E7D32] border border-transparent rounded-lg hover:bg-green-800 transition-colors w-max">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                    Change Photo
                                </button>
                                <button type="button" class="text-xs text-gray-500 hover:text-gray-700 text-left pl-1" x-on:click.prevent="photoPreview = null; $refs.photo.value = null">
                                    Remove Photo
                                </button>
                            </div>
                        </div>
                        @error('photo')
                            <p class="text-sm text-red-600 mt-2">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-6">
                        <label for="name" class="block text-xs font-bold text-gray-400 mb-1">Name</label>
                        <input id="name" name="name" type="text" class="block w-full border border-gray-200 rounded-md shadow-sm focus:border-green-500 focus:ring-green-500 sm:text-sm p-2.5 text-gray-900" value="{{ old('name', Auth::user()->name ?? 'Name') }}" required autofocus autocomplete="name" />
                        @error('name')
                            <p class="text-sm text-red-600 mt-2">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-6">
                        <label for="email" class="block text-xs font-bold text-gray-400 mb-1">Email Address</label>
                        <input id="email" name="email" type="email" class="border-gray-300 bg-gray-100 text-gray-500 cursor-not-allowed rounded-md shadow-sm mt-1 block w-full" value="{{ old('email', Auth::user()->email) }}" readonly />
                        <p class="mt-1.5 text-xs text-gray-400">Email address cannot be changed.</p>
                        @error('email')
                            <p class="text-sm text-red-600 mt-2">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-10">
                        <label class="block text-xs font-bold text-gray-400 mb-2">Role</label>
                        <div class="flex items-center">
                            <span class="text-xs font-bold text-[#2E7D32] bg-green-100 px-2 py-1 rounded-md mr-3">{{ ucfirst(Auth::user()->role) }}</span>
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
        <!-- PASSWORD VIEW -->
        <div x-show="currentView === 'password'" x-cloak x-transition.opacity class="max-w-2xl mx-auto">
            <div class="flex items-center mb-6">
                <button type="button" @click="currentView = 'main'" class="text-gray-400 hover:text-gray-600 mr-4">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </button>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Update Password</h1>
                    <p class="text-sm text-gray-500 mt-1">Ensure your account is using a long, random password to stay secure.</p>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8">
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

                    <div class="grid grid-cols-2 gap-4">
                        <button type="button" @click="currentView = 'main'" class="flex items-center justify-center px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="flex items-center justify-center px-4 py-2.5 text-sm font-medium text-white bg-[#2E7D32] border border-transparent rounded-lg hover:bg-green-800 transition-colors">
                            Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
        @endif

        <!-- SETTINGS VIEW -->
        <div x-show="currentView === 'settings'" x-cloak x-transition.opacity class="max-w-2xl mx-auto">
            <div class="flex items-center mb-6">
                <button type="button" @click="currentView = 'main'" class="text-gray-400 hover:text-gray-600 mr-4">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </button>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Account Settings</h1>
                    <p class="text-sm text-gray-500 mt-1">Manage your account preferences and data.</p>
                </div>
            </div>

            <h4 class="text-sm font-bold text-gray-700 mb-3">Account Details</h4>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-8">
                <div class="px-6 py-4 border-b border-gray-100">
                    <p class="text-xs text-gray-400 mb-1">Account Email</p>
                    <p class="text-sm font-medium text-gray-900">{{ Auth::user()->email ?? 'email@example.com' }}</p>
                </div>
                <div class="px-6 py-4">
                    <p class="text-xs text-gray-400 mb-1">Role</p>
                    <p class="text-sm font-medium text-gray-900">{{ ucfirst(Auth::user()->role) }}</p>
                </div>
            </div>

            @if ($canManagePassword)
            <h4 class="text-sm font-bold text-gray-700 mb-3">Security</h4>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-8">
                <button type="button" @click="currentView = 'password'" class="w-full flex items-center justify-between px-6 py-4 hover:bg-gray-50 transition-colors rounded-xl group text-left">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-gray-400 mr-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                        <div>
                            <p class="text-sm font-medium text-gray-900">Change Password</p>
                            <p class="text-xs text-gray-400 mt-0.5">Last changed recently</p>
                        </div>
                    </div>
                    <svg class="w-5 h-5 text-gray-300 group-hover:text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </button>
            </div>
            @endif

            <h4 class="text-sm font-bold text-red-500 mb-3">Danger Zone</h4>
            <div class="bg-white rounded-xl shadow-sm border border-red-200 divide-y divide-red-100">
                
                <div class="px-6 py-5">
                    <div class="flex items-start mb-4">
                        <div class="flex-shrink-0 mt-0.5 mr-4 text-orange-500">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path></svg>
                        </div>
                        <div>
                            <h5 class="text-sm font-bold text-gray-900">Deactivate Account</h5>
                            <p class="text-sm text-gray-500 mt-1 mb-4">Temporarily suspend your account. You can reactivate it by signing in again.</p>
                            <button type="button" @click="showDeactivateModal = true" class="inline-flex items-center px-4 py-2 border border-orange-200 text-sm font-medium rounded-lg text-orange-600 bg-white hover:bg-orange-50 transition-colors focus:outline-none">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path></svg>
                                Deactivate Account
                            </button>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-5">
                    <div class="flex items-start">
                        <div class="flex-shrink-0 mt-0.5 mr-4 text-red-500">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        </div>
                        <div>
                            <h5 class="text-sm font-bold text-gray-900">Delete Account</h5>
                            <p class="text-sm text-gray-500 mt-1 mb-4">Permanently delete your GreenPool account and associated account data.</p>
                            <button type="button" @click="showDeleteModal = true" class="inline-flex items-center px-4 py-2 border border-red-200 text-sm font-medium rounded-lg text-red-600 bg-white hover:bg-red-50 transition-colors focus:outline-none">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                Delete Account
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- MODALS & TOAST -->
        <div x-show="showToast" x-cloak x-transition.opacity.duration.300ms class="fixed bottom-8 left-1/2 transform -translate-x-1/2 z-40">
            <div class="flex items-center bg-gray-900 text-white px-5 py-3 rounded-xl shadow-lg">
                <div class="flex-shrink-0 mr-3">
                    <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
                <span class="text-sm font-medium whitespace-nowrap">Profile updated successfully.</span>
                <button @click="showToast = false" class="ml-6 text-gray-400 hover:text-white transition-colors focus:outline-none">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
        </div>

        <div x-show="showLogoutModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50" x-transition.opacity>
            <div @click.away="showLogoutModal = false" class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6 transform transition-all" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                <div class="flex items-center justify-between mb-5">
                    <h3 class="text-lg font-bold text-gray-900 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                        Log out?
                    </h3>
                    <button @click="showLogoutModal = false" class="text-gray-400 hover:text-gray-500 focus:outline-none">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <p class="text-sm text-gray-600 mb-6">Are you sure you want to log out of GreenPool?</p>
                <div class="flex items-center space-x-3">
                    <button @click="showLogoutModal = false" class="w-1/2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors focus:outline-none">
                        Cancel
                    </button>
                    <form method="POST" action="{{ route('logout') }}" class="w-1/2 m-0">
                        @csrf
                        <button type="submit" class="w-full px-4 py-2 text-sm font-medium text-white bg-[#2E7D32] border border-transparent rounded-lg hover:bg-green-800 transition-colors focus:outline-none">
                            Log Out
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div x-show="showDeactivateModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50" x-transition.opacity>
            <div @click.away="showDeactivateModal = false" class="bg-white rounded-xl shadow-xl w-full max-w-sm p-8 text-center transform transition-all" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                <div class="mx-auto mb-5 flex items-center justify-center w-14 h-14 rounded-full bg-orange-50 text-orange-500">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path></svg>
                </div>
                <h3 class="text-xl font-extrabold text-gray-900 mb-2">Deactivate your account?</h3>
                <p class="text-sm text-gray-500 mb-8">Your account will be temporarily suspended. You won't be able to access GreenPool until you sign in again to reactivate it.</p>
                <div class="flex flex-col space-y-3">
                    <button @click="showDeactivateModal = false" class="w-full px-4 py-3 text-sm font-bold text-white bg-[#2E7D32] rounded-lg hover:bg-green-800 transition-colors focus:outline-none">
                        Keep My Account Active
                    </button>
                    <form method="POST" action="{{ route('logout') }}" class="m-0">
                        @csrf
                        <button type="submit" class="w-full px-4 py-3 text-sm font-bold text-orange-400 bg-white border border-orange-200 rounded-lg hover:bg-orange-50 transition-colors focus:outline-none">
                            Yes, Deactivate Account
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div x-show="showDeleteModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50" x-transition.opacity>
            <div @click.away="showDeleteModal = false" class="bg-white rounded-xl shadow-xl w-full max-w-md p-6 transform transition-all" x-transition:enter="ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100" x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100" x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
                <h2 class="text-lg font-medium text-gray-900">Are you sure you want to delete your account?</h2>
                <p class="mt-1 text-sm text-gray-600">Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.</p>

                <form method="POST" action="{{ route('profile.destroy') }}" class="mt-6">
                    @csrf
                    @method('delete')

                    <div class="mt-6">
                        <label for="password" class="sr-only">Password</label>
                        <input id="password" name="password" type="password" class="block w-full border border-gray-200 rounded-md shadow-sm focus:border-red-500 focus:ring-red-500 sm:text-sm p-2.5 text-gray-900" placeholder="Password" required />
                        @error('password', 'userDeletion')
                            <p class="text-sm text-red-600 mt-2">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mt-6 flex justify-end space-x-3">
                        <button type="button" @click="showDeleteModal = false" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors focus:outline-none">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-lg hover:bg-red-700 transition-colors focus:outline-none">
                            Delete Account
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</x-app-layout>
