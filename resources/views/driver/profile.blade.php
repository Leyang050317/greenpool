<x-app-layout>
    <style>[x-cloak] { display: none !important; }</style>

    <div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8" x-data="{
        currentView: '{{ request('section') === 'licence' || $errors->hasAny(['driving_licence','holder_name','identity_no','licence_class','valid_from','valid_until']) ? 'licence' : ($errors->updatePassword->isNotEmpty() ? 'password' : ($errors->userDeletion->isNotEmpty() ? 'settings' : 'profile')) }}',
        editingProfile: {{ $errors->isNotEmpty() && ! $errors->updatePassword->isNotEmpty() && ! $errors->userDeletion->isNotEmpty() ? 'true' : 'false' }},
        showLogoutModal: false, showDeleteModal: {{ $errors->userDeletion->isNotEmpty() ? 'true' : 'false' }}, showDisconnectGoogleModal: false,
        showCurrentPassword: false, showNewPassword: false, showConfirmPassword: false, newPassword: '',
        get passwordScore() { let score = 0; if (this.newPassword.length >= 8) score++; if (/[a-z]/.test(this.newPassword) && /[A-Z]/.test(this.newPassword)) score++; if (/\d/.test(this.newPassword)) score++; if (/[^A-Za-z0-9]/.test(this.newPassword)) score++; return score; },
        get passwordLabel() { return ['Very weak', 'Weak', 'Medium', 'Strong', 'Very strong'][this.passwordScore]; },
        get passwordWidth() { return (this.passwordScore * 25) + '%'; },
        get passwordColor() { return ['bg-red-500', 'bg-red-500', 'bg-amber-400', 'bg-green-500', 'bg-green-600'][this.passwordScore]; }
    }">
        <div class="mb-8">
            <div><p class="text-sm font-semibold text-[#2E7D32]">Driver account</p><h2 class="mt-1 text-3xl font-bold tracking-tight text-gray-950">My Profile</h2><p class="mt-2 text-sm text-gray-600">Manage your account, vehicles, and driving preferences.</p></div>
        </div>

        <div><x-profile-completeness-card :profile-completeness="$profileCompleteness" /></div>

        @if (session('error'))<div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800" role="alert">{{ session('error') }}</div>@endif

        <div x-show="!['password', 'settings'].includes(currentView)" class="mt-6 overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="flex gap-1 overflow-x-auto border-b border-gray-200 p-1.5" role="tablist" aria-label="Driver profile sections">
                @foreach ([['profile', 'Personal Info', 'user-round'], ['licence', 'Driving Licence', 'badge-check'], ['vehicles', 'My Vehicles', 'car-front'], ['preferences', 'Preferences', 'sliders-horizontal']] as [$view, $label, $icon])
                    <button type="button" @click="currentView = '{{ $view }}'" :class="currentView === '{{ $view }}' ? 'bg-green-50 text-[#2E7D32]' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-800'" class="inline-flex min-h-10 shrink-0 items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold transition" role="tab" :aria-selected="currentView === '{{ $view }}'"><x-icons.lucide :name="$icon" class="h-4 w-4" />{{ $label }}</button>
                @endforeach
            </div>

            <div x-show="currentView === 'profile'" x-transition.opacity>
                <div class="flex flex-col gap-5 border-b border-gray-100 p-6 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex min-w-0 items-center gap-4">
                        @if ($user->photo)<img src="{{ asset('storage/'.$user->photo) }}" alt="Profile photo for {{ $user->name }}" class="h-20 w-20 rounded-full border border-gray-200 object-cover">@else<span class="flex h-20 w-20 shrink-0 items-center justify-center rounded-full bg-[#2E7D32] text-2xl font-bold text-white">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>@endif
                        <div class="min-w-0"><h3 class="truncate text-xl font-bold text-gray-950">{{ $user->name }}</h3><p class="mt-1 truncate text-sm text-gray-500">{{ $user->email }}</p><div class="mt-3 flex flex-wrap items-center gap-2"><span class="rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-[#2E7D32]">Driver</span><a href="{{ route('ratings.received', $user) }}" class="inline-flex items-center gap-1 text-xs font-semibold text-amber-600 hover:text-amber-700"><x-icons.lucide name="star" class="h-3.5 w-3.5 fill-current" />{{ $user->ratings_received_count ? number_format((float) $user->ratings_received_avg_score, 1) : 'No ratings' }}<span class="font-normal text-gray-400">({{ $user->ratings_received_count }} {{ Str::plural('review', $user->ratings_received_count) }})</span></a></div></div>
                    </div>
                    <button type="button" @click="editingProfile = ! editingProfile" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"><x-icons.lucide name="pencil" class="h-4 w-4" /><span x-text="editingProfile ? 'Cancel' : 'Edit Profile'"></span></button>
                </div>
                <div class="grid divide-y divide-gray-100 sm:grid-cols-3 sm:divide-x sm:divide-y-0"><div class="p-5"><p class="text-xs font-medium uppercase tracking-wide text-gray-400">Name</p><p class="mt-2 text-sm font-semibold text-gray-900">{{ $user->name }}</p></div><div class="p-5"><p class="text-xs font-medium uppercase tracking-wide text-gray-400">Email Address</p><p class="mt-2 truncate text-sm font-semibold text-gray-900">{{ $user->email }}</p></div><div class="p-5"><p class="text-xs font-medium uppercase tracking-wide text-gray-400">Account Type</p><p class="mt-2 text-sm font-semibold text-gray-900">{{ $user->usesGoogleAuthentication() ? 'Google account' : 'Password account' }}</p></div></div>

                <div class="px-6 pb-6">
            <section x-cloak x-show="editingProfile" x-transition.opacity class="mt-6 rounded-2xl border border-gray-200 bg-white p-6"><h3 class="text-lg font-bold text-gray-950">Edit Personal Information</h3><p class="mt-1 text-sm text-gray-600">Your email address and role are locked for account security.</p><form method="POST" action="{{ route('driver.profile.update') }}" enctype="multipart/form-data" class="mt-6 space-y-5" x-data="{ preview: null }">@csrf @method('PATCH')
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center"><div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-full bg-green-100 text-[#2E7D32]"><template x-if="preview"><img :src="preview" alt="Profile photo preview" class="h-full w-full object-cover"></template><template x-if="!preview">@if ($user->photo)<img src="{{ asset('storage/'.$user->photo) }}" alt="Profile photo for {{ $user->name }}" class="h-full w-full object-cover">@else<span class="text-xl font-bold">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>@endif</template></div><label class="inline-flex w-fit cursor-pointer items-center gap-2 rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"><x-icons.lucide name="image-up" class="h-4 w-4" />Change Photo<input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="const file = $event.target.files[0]; if (file) preview = URL.createObjectURL(file)"></label></div>
                @error('photo')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                <div class="grid gap-5 sm:grid-cols-2"><div><label for="driver_name" class="mb-1.5 block text-sm font-semibold text-gray-700">Name</label><input id="driver_name" name="name" type="text" value="{{ old('name', $user->name) }}" required autocomplete="name" class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-[#2E7D32] focus:ring-[#2E7D32]"><x-input-error :messages="$errors->get('name')" class="mt-2" /></div><div><label for="driver_email" class="mb-1.5 block text-sm font-semibold text-gray-700">Email Address</label><input id="driver_email" type="email" value="{{ $user->email }}" readonly disabled class="block w-full cursor-not-allowed rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-500"><p class="mt-1.5 text-xs text-gray-400">Email address cannot be changed.</p></div></div>
                <div class="flex justify-end gap-3"><button type="button" @click="editingProfile = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancel</button><button type="submit" class="rounded-lg bg-[#2E7D32] px-4 py-2 text-sm font-semibold text-white hover:bg-[#256b29]">Save Changes</button></div>
            </form></section>
            <div class="mt-6"><x-phone-verification-card :user="$user" /></div>
            <div class="mt-6"><x-emergency-contacts-card :user="$user" /></div>
            <section class="mt-8">
                <h3 class="mb-3 text-base font-bold text-gray-900">Account</h3>
                <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                    @if ($canManagePassword)
                    <button type="button" @click="currentView = 'password'; $nextTick(() => window.scrollTo({ top: 0, behavior: 'smooth' }))" class="group flex w-full items-center justify-between border-b border-gray-100 px-6 py-4 text-left transition-colors hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#2E7D32]">
                        <span class="flex items-center gap-4">
                            <x-icons.lucide name="lock-keyhole" class="h-5 w-5 text-gray-400" />
                            <span><span class="block text-sm font-semibold text-gray-900">Change Password</span><span class="mt-0.5 block text-xs text-gray-400">Update your account password</span></span>
                        </span>
                        <x-icons.lucide name="chevron-right" class="h-5 w-5 text-gray-300 group-hover:text-gray-500" />
                    </button>
                    @endif
                    <button type="button" @click="currentView = 'settings'; $nextTick(() => window.scrollTo({ top: 0, behavior: 'smooth' }))" class="group flex w-full items-center justify-between border-b border-gray-100 px-6 py-4 text-left transition-colors hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#2E7D32]">
                        <span class="flex items-center gap-4">
                            <x-icons.lucide name="shield-check" class="h-5 w-5 text-gray-400" />
                            <span><span class="block text-sm font-semibold text-gray-900">Account Settings</span><span class="mt-0.5 block text-xs text-gray-400">Manage account data</span></span>
                        </span>
                        <x-icons.lucide name="chevron-right" class="h-5 w-5 text-gray-300 group-hover:text-gray-500" />
                    </button>
                    <button type="button" @click="showLogoutModal = true" class="group flex w-full items-center justify-between px-6 py-4 text-left transition-colors hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-[#2E7D32]">
                        <span class="flex items-center gap-4"><x-icons.lucide name="log-out" class="h-5 w-5 text-gray-400" /><span class="text-sm font-semibold text-gray-900">Log Out</span></span>
                        <x-icons.lucide name="chevron-right" class="h-5 w-5 text-gray-300 group-hover:text-gray-500" />
                    </button>
                </div>
                </div>
            </section>
            </div>

            <div x-cloak x-show="currentView === 'licence'" x-transition.opacity>
            <section class="p-6" x-data="{
                busy: false, message: '', showLicenceExamples: false, preview: {{ $driverLicence && ! $errors->hasAny(['driving_licence','holder_name','identity_no','licence_class','valid_from','valid_until']) ? Js::from(route('driver.profile.driving-licence.image')) : 'null' }}, scanned: false, licenceClasses: @js(\App\Support\MalaysianDrivingLicenceClass::parse($driverLicence?->licence_class)), classDescriptions: { B2: 'Motorcycle not exceeding 250cc', B: 'Motorcycle exceeding 500cc', D: 'Manual and automatic motorcar up to 3,500kg', DA: 'Automatic motorcar up to 3,500kg', E: 'Heavy motorcar', F: 'Tractor or light mobile machinery', G: 'Tractor or light mobile machinery', H: 'Heavy tractor or mobile machinery', I: 'Heavy tractor or mobile machinery' },
                parseLicenceClasses(value){const compact=(value||'').toUpperCase().replace(/[^A-Z0-9]/g,'');const known=['B2','A1','DA','A','B','C','D','E','F','G','H','I'];const result=[];let remaining=compact;while(remaining){const match=known.find(item=>remaining.startsWith(item));if(match){if(!result.includes(match))result.push(match);remaining=remaining.slice(match.length)}else remaining=remaining.slice(1)}return result;},
                snackbar: { show: {{ $errors->hasAny(['driving_licence','holder_name','identity_no','licence_class','valid_from','valid_until']) ? 'true' : 'false' }}, message: @js($errors->hasAny(['driving_licence','holder_name','identity_no','licence_class','valid_from','valid_until']) ? implode(' ', collect(['driving_licence','holder_name','identity_no','licence_class','valid_from','valid_until'])->flatMap(fn ($field) => $errors->get($field))->all()) : '') },
                init() { if (this.snackbar.show) setTimeout(() => this.snackbar.show = false, 8000); },
                dateValue(value) { const match=(value||'').match(/^(\d{2})[\/.\-](\d{2})[\/.\-](\d{4})$/); return match ? `${match[3]}-${match[2]}-${match[1]}` : value; },
                async scan() {
                    const file=this.$refs.file.files[0]; if(!file){this.message='Choose a driving licence image first.';return;}
                    this.busy=true;this.message='Scanning licence…';const body=new FormData();body.append('document',file);body.append('expected_document_type','DRIVING_LICENCE');
                    try { const response=await fetch('{{ route('driver.vehicles.documents.ocr') }}',{method:'POST',headers:{'Accept':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},body});const data=await response.json();if(!response.ok)throw new Error(data.message||'Unable to scan the licence.');const fields=data.fields||{};const set=(name,key,date=false)=>{const value=fields[key]?.value;if(value)this.$refs[name].value=date?this.dateValue(value):value};set('holder','name');set('identity','identity_no');set('class','licence_class');set('from','valid_from',true);set('until','valid_until',true);this.licenceClasses=this.parseLicenceClasses(this.$refs.class.value);this.scanned=true;this.message='Scan complete. Extracted details are locked and cannot be edited.'; }
                    catch(error){this.scanned=false;this.message=error.message;} finally{this.busy=false;}
                }
            }">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"><div><h3 class="text-xl font-bold text-gray-950">Driving Licence</h3><p class="mt-1 text-sm text-gray-600">Upload a licence to create trips. A valid licence is required when starting a trip.</p></div>@if($driverLicence)<span class="rounded-full px-3 py-1.5 text-xs font-semibold {{ $driverLicence->isValidOn(now()) ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">{{ $driverLicence->isValidOn(now()) ? 'Valid until '.$driverLicence->valid_until->format('d M Y') : 'Expired or unverified' }}</span>@endif</div>
                <form method="POST" action="{{ route('driver.profile.driving-licence.update') }}" enctype="multipart/form-data" class="mt-6 space-y-5">@csrf @method('PUT')
                    <div><div class="flex items-center justify-between gap-4"><p class="text-sm font-semibold text-gray-700">Driving licence image *</p><button type="button" @click="showLicenceExamples=true" class="shrink-0 text-sm font-semibold text-blue-700 underline decoration-blue-300 underline-offset-4 hover:text-blue-900">View licence examples</button></div><label class="mx-auto mt-2 flex h-48 max-w-2xl cursor-pointer items-center justify-center overflow-hidden rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 sm:h-64"><input x-ref="file" name="driving_licence" type="file" accept="image/jpeg,image/png" required class="sr-only" @change="preview=URL.createObjectURL($event.target.files[0]);scanned=false;message='Scan this newly selected image before saving.'"><img x-cloak x-show="preview" :src="preview" alt="Driving licence preview" class="h-full w-full object-contain p-1"><span x-show="!preview" class="p-6 text-center text-sm text-gray-500">Choose a clear JPG or PNG image</span></label></div>
                    <div x-cloak x-show="showLicenceExamples" x-transition.opacity class="fixed inset-0 z-[100] flex items-center justify-center bg-gray-950/70 p-4" @keydown.escape.window="showLicenceExamples=false"><div class="w-full max-w-5xl overflow-hidden rounded-2xl bg-white shadow-2xl" @click.outside="showLicenceExamples=false"><div class="flex items-start justify-between border-b border-gray-100 px-5 py-4"><div><h2 class="font-bold text-gray-950">Driving licence examples</h2><p class="mt-1 text-xs text-gray-500">You may upload a MyJPJ digital licence screenshot or a clear photo of the physical card.</p></div><button type="button" @click="showLicenceExamples=false" class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-700" aria-label="Close licence examples"><x-icons.lucide name="x" class="h-5 w-5" /></button></div><div class="grid max-h-[78vh] items-start gap-5 overflow-auto bg-gray-100 p-4 md:grid-cols-2 sm:p-6"><figure class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm"><div class="flex h-[28rem] items-center justify-center bg-gray-50 p-3"><img src="{{ asset('images/vehicle-photo-examples/licence-myjpj-example.png') }}" alt="MyJPJ digital driving licence screenshot example using fictional data" class="h-full w-full object-contain"></div><figcaption class="border-t border-gray-200 px-4 py-3"><p class="font-semibold text-gray-900">MyJPJ digital licence</p><p class="mt-1 text-xs leading-5 text-gray-500">Upload the full screenshot with the holder, class and validity dates clearly visible.</p></figcaption></figure><figure class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm"><div class="flex h-[28rem] items-center justify-center bg-gray-50 p-3"><img src="{{ asset('images/vehicle-photo-examples/licence-card-example.png') }}" alt="Physical driving licence card photo example using fictional data" class="max-h-full w-full object-contain"></div><figcaption class="border-t border-gray-200 px-4 py-3"><p class="font-semibold text-gray-900">Physical licence card</p><p class="mt-1 text-xs leading-5 text-gray-500">Photograph the whole card straight-on in good light, without glare, blur or cropped corners.</p></figcaption></figure></div></div></div>
                    <div x-cloak x-show="busy" class="mx-auto max-w-2xl rounded-lg border border-blue-100 bg-blue-50 px-4 py-3"><div class="mb-2 flex items-center justify-between text-xs font-semibold text-blue-800"><span>Scanning driving licence</span><span>Please wait…</span></div><div class="h-2 overflow-hidden rounded-full bg-blue-100"><div class="scan-progress-bar h-full w-full rounded-full"></div></div><p class="mt-2 text-xs text-blue-700">Reading the licence holder, identity number, class and validity dates.</p></div><button x-show="!busy" type="button" @click="scan" class="rounded-lg border border-[#2E7D32] px-4 py-2 text-sm font-semibold text-[#2E7D32] hover:bg-green-50">Scan & prefill</button><p x-show="message && !busy" x-text="message" class="text-sm text-gray-600"></p>
                    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3"><label class="text-sm font-semibold text-gray-700">Holder name *<input x-ref="holder" name="holder_name" value="{{ $errors->hasAny(['driving_licence','holder_name','identity_no','licence_class','valid_from','valid_until']) ? '' : $driverLicence?->holder_name }}" required readonly class="mt-2 block w-full cursor-not-allowed rounded-lg border-gray-200 bg-gray-50 text-gray-600 uppercase"></label><label class="text-sm font-semibold text-gray-700">Identity number *<input x-ref="identity" name="identity_no" value="{{ $errors->hasAny(['driving_licence','holder_name','identity_no','licence_class','valid_from','valid_until']) ? '' : $driverLicence?->identity_no }}" required readonly inputmode="numeric" maxlength="12" pattern="[0-9]{12}" class="mt-2 block w-full cursor-not-allowed rounded-lg border-gray-200 bg-gray-50 text-gray-600"></label><div><label class="text-sm font-semibold text-gray-700">Licence class<input x-ref="class" name="licence_class" value="{{ $errors->hasAny(['driving_licence','holder_name','identity_no','licence_class','valid_from','valid_until']) ? '' : $driverLicence?->licence_class }}" readonly class="mt-2 block w-full cursor-not-allowed rounded-lg border-gray-200 bg-gray-50 text-gray-600"></label><div x-cloak x-show="licenceClasses.length" class="mt-2 space-y-1.5"><template x-for="licenceClass in licenceClasses" :key="licenceClass"><div class="flex items-start gap-2 text-xs"><span class="inline-flex min-w-9 justify-center rounded-full bg-green-100 px-2 py-1 font-bold text-green-800" x-text="licenceClass"></span><span class="pt-1 text-gray-500" x-text="classDescriptions[licenceClass] || 'Additional Malaysian driving licence class'"></span></div></template></div><p x-show="licenceClasses.length && !licenceClasses.some(item => item === 'D' || item === 'DA')" class="mt-2 text-xs font-medium text-red-600">Class D or DA is required to provide carpool trips.</p></div><label class="text-sm font-semibold text-gray-700">Valid from<input x-ref="from" name="valid_from" type="date" value="{{ $errors->hasAny(['driving_licence','holder_name','identity_no','licence_class','valid_from','valid_until']) ? '' : $driverLicence?->valid_from?->toDateString() }}" readonly class="mt-2 block w-full cursor-not-allowed rounded-lg border-gray-200 bg-gray-50 text-gray-600"></label><label class="text-sm font-semibold text-gray-700">Valid until *<input x-ref="until" name="valid_until" type="date" value="{{ $errors->hasAny(['driving_licence','holder_name','identity_no','licence_class','valid_from','valid_until']) ? '' : $driverLicence?->valid_until?->toDateString() }}" required readonly class="mt-2 block w-full cursor-not-allowed rounded-lg border-gray-200 bg-gray-50 text-gray-600"></label></div>
                    <div class="flex justify-end"><button type="submit" :disabled="!scanned || busy" class="rounded-lg bg-[#2E7D32] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#256b29] disabled:cursor-not-allowed disabled:opacity-50">Save driving licence</button></div>
                </form>
                <div x-cloak x-show="snackbar.show" x-transition class="fixed inset-x-4 bottom-5 z-50 mx-auto flex max-w-xl items-start gap-3 rounded-xl bg-gray-950 px-4 py-3 text-sm text-white shadow-2xl sm:inset-x-auto sm:right-6" role="alert" aria-live="assertive"><span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-red-500 font-bold">!</span><p x-text="snackbar.message" class="min-w-0 flex-1 leading-5"></p><button type="button" @click="snackbar.show=false" class="rounded p-1 text-gray-300 hover:bg-white/10 hover:text-white" aria-label="Dismiss message">×</button></div>
            </section>
            </div>

            <div x-cloak x-show="currentView === 'vehicles'" x-transition.opacity><section class="p-6"><div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"><div><h3 class="text-xl font-bold text-gray-950">My Vehicles</h3><p class="mt-1 text-sm text-gray-600">Vehicles are available for trip management once verified and active.</p></div><a href="{{ route('driver.vehicles.create') }}" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg bg-[#2E7D32] px-4 py-2 text-sm font-semibold text-white hover:bg-[#256b29]"><x-icons.lucide name="plus" class="h-4 w-4" />Add Vehicle</a></div>
            @forelse ($vehicles as $vehicle)<article class="mt-5 flex flex-col gap-4 rounded-xl border border-gray-200 p-4 sm:flex-row sm:items-center"><div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-gray-100 text-gray-400">@if ($vehicle->vehicle_image_path)<img src="{{ asset('storage/'.$vehicle->vehicle_image_path) }}" alt="{{ $vehicle->brand }} {{ $vehicle->model }}" class="h-full w-full object-cover">@else<x-icons.lucide name="car-front" class="h-8 w-8" />@endif</div><div class="min-w-0 flex-1"><h4 class="truncate text-base font-bold text-gray-950">{{ $vehicle->brand }} {{ $vehicle->model }}</h4><p class="mt-1 font-mono text-sm text-gray-500">{{ $vehicle->plate_number }}</p><p class="mt-1 text-xs text-gray-500">{{ $vehicle->colour }} · {{ $vehicle->seat_capacity }} {{ Str::plural('seat', $vehicle->seat_capacity) }}</p></div><div class="flex flex-wrap items-center gap-2"><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $vehicle->status === 'Active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">{{ $vehicle->status }}</span><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $vehicle->verification_status === 'Verified' ? 'bg-green-100 text-green-700' : ($vehicle->verification_status === 'Rejected' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700') }}">{{ $vehicle->verification_status }}</span><a href="{{ route('driver.vehicles.show', $vehicle) }}" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Manage</a></div></article>
            @empty<div class="mt-6 rounded-xl border border-dashed border-gray-300 px-6 py-12 text-center"><span class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-gray-100 text-gray-400"><x-icons.lucide name="car-front" class="h-6 w-6" /></span><h4 class="mt-4 font-bold text-gray-900">No vehicles registered</h4><p class="mt-2 text-sm text-gray-500">Add and verify a vehicle before creating a trip.</p></div>@endforelse
            <a href="{{ route('driver.vehicles.index') }}" class="mt-6 inline-flex items-center gap-2 text-sm font-semibold text-[#2E7D32] hover:text-[#256b29]">Open full vehicle management <x-icons.lucide name="arrow-right" class="h-4 w-4" /></a>
        </section></div>

            <div x-cloak x-show="currentView === 'preferences'" x-transition.opacity><section class="p-6"><h3 class="text-xl font-bold text-gray-950">Driving Preferences</h3><p class="mt-1 text-sm text-gray-600">These preferences help passengers understand your ride environment.</p><form method="POST" action="{{ route('driver.profile.preferences.update') }}" class="mt-6 space-y-5">@csrf @method('PUT')
            <label class="flex cursor-pointer items-center justify-between gap-6 rounded-xl border border-gray-200 p-4 hover:border-green-200"><span><span class="block text-sm font-semibold text-gray-900">Smoking allowed</span><span class="mt-1 block text-sm text-gray-500">Passengers may smoke during the trip.</span></span><span class="relative inline-flex h-6 w-11 shrink-0 items-center"><input type="hidden" name="smoking_allowed" value="0"><input type="checkbox" name="smoking_allowed" value="1" class="peer sr-only" @checked(old('smoking_allowed', $preference->smoking_allowed))><span class="h-6 w-11 rounded-full bg-gray-200 transition peer-checked:bg-[#2E7D32]"></span><span class="absolute left-1 h-4 w-4 rounded-full bg-white transition peer-checked:translate-x-5"></span></span></label>
            <label class="flex cursor-pointer items-center justify-between gap-6 rounded-xl border border-gray-200 p-4 hover:border-green-200"><span><span class="block text-sm font-semibold text-gray-900">Pets allowed</span><span class="mt-1 block text-sm text-gray-500">Passengers may bring a pet with prior coordination.</span></span><span class="relative inline-flex h-6 w-11 shrink-0 items-center"><input type="hidden" name="pets_allowed" value="0"><input type="checkbox" name="pets_allowed" value="1" class="peer sr-only" @checked(old('pets_allowed', $preference->pets_allowed))><span class="h-6 w-11 rounded-full bg-gray-200 transition peer-checked:bg-[#2E7D32]"></span><span class="absolute left-1 h-4 w-4 rounded-full bg-white transition peer-checked:translate-x-5"></span></span></label>
            <div><label for="conversation_preference" class="mb-1.5 block text-sm font-semibold text-gray-900">Conversation preference</label><p class="mb-2 text-sm text-gray-500">Set the atmosphere you prefer while driving.</p><select id="conversation_preference" name="conversation_preference" class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-[#2E7D32] focus:ring-[#2E7D32]">@foreach (['Quiet', 'Moderate', 'Chatty'] as $option)<option value="{{ $option }}" @selected(old('conversation_preference', $preference->conversation_preference) === $option)>{{ $option }}</option>@endforeach</select><x-input-error :messages="$errors->get('conversation_preference')" class="mt-2" /></div><div class="flex justify-end"><button type="submit" class="rounded-lg bg-[#2E7D32] px-4 py-2 text-sm font-semibold text-white hover:bg-[#256b29]">Save Preferences</button></div>
        </form></section></div>
        </div>

        {{-- ===== Change Password View ===== --}}
        @if ($canManagePassword)
        <div x-cloak x-show="currentView === 'password'" x-transition.opacity class="max-w-2xl mx-auto">
            <div class="flex items-center mb-6">
                <button type="button" @click="currentView = 'profile'" class="text-gray-400 hover:text-gray-600 mr-4">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                </button>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Update Password</h1>
                    <p class="text-sm text-gray-500 mt-1">Use a strong, unique password to keep your account secure.</p>
                </div>
            </div>

            <section class="rounded-2xl border border-gray-200 bg-white p-6">
                <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
                    @csrf @method('PUT')

                    <div>
                        <label for="current_password" class="mb-1.5 block text-sm font-semibold text-gray-700">Current Password</label>
                        <div class="relative">
                            <input id="current_password" name="current_password" :type="showCurrentPassword ? 'text' : 'password'" required autocomplete="current-password" class="block w-full rounded-lg border-gray-300 pr-12 text-sm shadow-sm focus:border-[#2E7D32] focus:ring-[#2E7D32]">
                            <button type="button" @click="showCurrentPassword = !showCurrentPassword" class="absolute inset-y-0 right-0 px-3 text-gray-400 hover:text-gray-700" :aria-label="showCurrentPassword ? 'Hide current password' : 'Show current password'"><x-icons.lucide name="eye" class="h-5 w-5" /></button>
                        </div>
                        <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
                    </div>

                    <div>
                        <label for="new_password" class="mb-1.5 block text-sm font-semibold text-gray-700">New Password</label>
                        <div class="relative">
                            <input id="new_password" name="password" x-model="newPassword" :type="showNewPassword ? 'text' : 'password'" required autocomplete="new-password" class="block w-full rounded-lg border-gray-300 pr-12 text-sm shadow-sm focus:border-[#2E7D32] focus:ring-[#2E7D32]">
                            <button type="button" @click="showNewPassword = !showNewPassword" class="absolute inset-y-0 right-0 px-3 text-gray-400 hover:text-gray-700" :aria-label="showNewPassword ? 'Hide new password' : 'Show new password'"><x-icons.lucide name="eye" class="h-5 w-5" /></button>
                        </div>
                        <div class="mt-3" x-show="newPassword.length" x-cloak>
                            <div class="flex justify-between text-xs">
                                <span class="text-gray-500">Password strength</span>
                                <span x-text="passwordLabel" :class="passwordScore >= 3 ? 'text-green-700' : 'text-amber-600'" class="font-semibold"></span>
                            </div>
                            <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-gray-100">
                                <div class="h-full rounded-full transition-all" :class="passwordColor" :style="{ width: passwordWidth }"></div>
                            </div>
                            <p class="mt-2 text-xs text-gray-500">Use 8+ characters with uppercase, lowercase, a number, and a symbol.</p>
                        </div>
                        <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
                    </div>

                    <div>
                        <label for="password_confirmation" class="mb-1.5 block text-sm font-semibold text-gray-700">Confirm New Password</label>
                        <div class="relative">
                            <input id="password_confirmation" name="password_confirmation" :type="showConfirmPassword ? 'text' : 'password'" required autocomplete="new-password" class="block w-full rounded-lg border-gray-300 pr-12 text-sm shadow-sm focus:border-[#2E7D32] focus:ring-[#2E7D32]">
                            <button type="button" @click="showConfirmPassword = !showConfirmPassword" class="absolute inset-y-0 right-0 px-3 text-gray-400 hover:text-gray-700" :aria-label="showConfirmPassword ? 'Hide confirmation password' : 'Show confirmation password'"><x-icons.lucide name="eye" class="h-5 w-5" /></button>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <button type="button" @click="currentView = 'profile'" class="flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">Cancel</button>
                        <button type="submit" class="flex items-center justify-center px-4 py-2.5 text-sm font-semibold text-white bg-[#2E7D32] border border-transparent rounded-lg hover:bg-[#256b29] transition-colors">Update Password</button>
                    </div>
                </form>
            </section>
        </div>
        @endif

        {{-- ===== Account Settings View ===== --}}
        <div x-cloak x-show="currentView === 'settings'" x-transition.opacity class="max-w-2xl mx-auto">
            <div class="flex items-center mb-6">
                <button type="button" @click="currentView = 'profile'" class="text-gray-400 hover:text-gray-600 mr-4">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
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
                    <p class="text-sm font-medium text-gray-900">{{ $user->email }}</p>
                </div>
                <div class="px-6 py-4">
                    <p class="text-xs text-gray-400 mb-1">Role</p>
                    <p class="text-sm font-medium text-gray-900">{{ ucfirst($user->role) }}</p>
                </div>
            </div>

            <h4 class="text-sm font-bold text-gray-700 mb-3">Linked Accounts</h4>
            <div class="mb-8"><x-linked-accounts.google-card :user="$user" /></div>

            <h4 class="text-sm font-bold text-gray-700 mb-3">Recent Login Activity</h4>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-8 px-6 py-2">
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

            <h4 class="text-sm font-bold text-red-500 mb-3">Danger Zone</h4>
            <div class="bg-white rounded-xl shadow-sm border border-red-200 px-6 py-5">
                <div class="flex items-start">
                    <div class="flex-shrink-0 mt-0.5 mr-4 text-red-500">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    </div>
                    <div>
                        <h5 class="text-sm font-bold text-gray-900">Delete Account</h5>
                        <p class="text-sm text-gray-500 mt-1 mb-4">Permanently delete your GreenPool account and associated account data.</p>
                        <button type="button" @click="showDeleteModal = true" class="inline-flex items-center px-4 py-2 border border-red-200 text-sm font-medium rounded-lg text-red-600 bg-white hover:bg-red-50 transition-colors">Delete Account</button>
                    </div>
                </div>
            </div>
        </div>

        @if (session('status'))<div class="fixed bottom-6 right-6 z-40 rounded-xl bg-gray-900 px-4 py-3 text-sm font-semibold text-white shadow-lg" x-data="{ open: true }" x-init="setTimeout(() => open = false, 6000)" x-show="open" x-transition role="status"><span>{{ match(session('status')) { 'profile-updated' => 'Profile updated successfully.', 'driver-preferences-updated' => 'Driving preferences saved successfully.', 'driver-licence-updated' => 'Driving licence verified and saved successfully.', 'password-updated' => 'Password updated successfully.', 'google-account-linked' => 'Google account connected successfully.', 'google-account-unlinked' => 'Google account disconnected successfully.', default => 'Changes saved successfully.' } }}</span><button type="button" @click="open = false" class="ml-4 text-gray-300 hover:text-white" aria-label="Dismiss notification">×</button></div>@endif
        <div x-cloak x-show="showLogoutModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" x-transition.opacity><div @click.away="showLogoutModal = false" class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-xl"><h3 class="text-lg font-bold text-gray-950">Log out?</h3><p class="mt-2 text-sm text-gray-600">Are you sure you want to log out of GreenPool?</p><div class="mt-6 flex gap-3"><button type="button" @click="showLogoutModal = false" class="flex-1 rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancel</button><form method="POST" action="{{ route('logout') }}" class="flex-1">@csrf<button type="submit" class="w-full rounded-lg bg-[#2E7D32] px-4 py-2 text-sm font-semibold text-white hover:bg-[#256b29]">Log Out</button></form></div></div></div>
        <div x-cloak x-show="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" x-transition.opacity><div @click.away="showDeleteModal = false" class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl"><h3 class="text-lg font-bold text-gray-950">Delete your account?</h3><p class="mt-2 text-sm text-gray-600">This action cannot be undone. Enter your password to confirm.</p><form method="POST" action="{{ route('profile.destroy') }}" class="mt-5">@csrf @method('DELETE')<label for="delete_password" class="sr-only">Password</label><input id="delete_password" name="password" type="password" required autocomplete="current-password" placeholder="Password" class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-red-500 focus:ring-red-500"><x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" /><div class="mt-6 flex justify-end gap-3"><button type="button" @click="showDeleteModal = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancel</button><button type="submit" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">Delete Account</button></div></form></div></div>
    </div>
</x-app-layout>
