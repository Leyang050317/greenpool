@props(['user'])

@php
    $contacts = $user->emergencyContacts;
@endphp

<section
    class="rounded-2xl border border-gray-200 bg-white p-6"
    x-data="{
        showContactModal: {{ $errors->emergencyContact->hasAny(['name', 'relationship', 'phone_number', 'emergency_contacts']) ? 'true' : 'false' }},
        editingContact: null,
        contacts: @js($contacts->map(fn ($contact) => [
            'id' => $contact->id,
            'name' => $contact->name,
            'relationship' => $contact->relationship,
            'phone_number' => $contact->phone_number,
        ])->values()),
        form: {
            name: @js(old('name')),
            relationship: @js(old('relationship')),
            phone_number: @js(old('phone_number')),
        },
        openCreate() {
            this.editingContact = null;
            this.form = { name: '', relationship: '', phone_number: '' };
            this.showContactModal = true;
        },
        openEdit(contact) {
            this.editingContact = contact;
            this.form = {
                name: contact.name,
                relationship: contact.relationship,
                phone_number: contact.phone_number,
            };
            this.showContactModal = true;
        }
    }"
>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex items-start gap-3">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-green-50 text-[#2E7D32]">
                <x-icons.lucide name="users" class="h-5 w-5" />
            </span>
            <div>
                <h3 class="text-lg font-bold text-gray-950">Emergency Contacts</h3>
                <p class="mt-1 text-sm text-gray-600">Save trusted contacts for additional trip safety.</p>
            </div>
        </div>
        <button type="button" @click="openCreate()" :disabled="contacts.length >= 3" class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg bg-[#2E7D32] px-4 py-2 text-sm font-semibold text-white hover:bg-[#256b29] disabled:cursor-not-allowed disabled:bg-gray-300">
            <x-icons.lucide name="plus" class="h-4 w-4" />
            Add Contact
        </button>
    </div>

    @if (session('status') === 'emergency-contact-created' || session('status') === 'emergency-contact-updated' || session('status') === 'emergency-contact-deleted')
        <div class="mt-5 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-800">
            {{ match(session('status')) {
                'emergency-contact-created' => 'Emergency contact added successfully.',
                'emergency-contact-updated' => 'Emergency contact updated successfully.',
                default => 'Emergency contact removed successfully.',
            } }}
        </div>
    @endif

    <x-input-error :messages="$errors->emergencyContact->get('emergency_contacts')" class="mt-4" />

    <div class="mt-5 space-y-3">
        @forelse ($contacts as $contact)
            <article class="flex flex-col gap-4 rounded-xl border border-gray-200 p-4 sm:flex-row sm:items-center">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-green-100 text-sm font-bold text-[#2E7D32]">{{ mb_strtoupper(mb_substr($contact->name, 0, 1)) }}</span>
                <div class="min-w-0 flex-1">
                    <h4 class="truncate text-sm font-bold text-gray-950">{{ $contact->name }}</h4>
                    <p class="mt-1 text-sm text-gray-500">{{ $contact->relationship }} · {{ $contact->phone_number }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" @click="openEdit(contacts.find(contact => contact.id === {{ $contact->id }}))" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Edit</button>
                    <form method="POST" action="{{ route('emergency-contacts.destroy', $contact) }}" onsubmit="return confirm('Remove this emergency contact?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="rounded-lg border border-red-200 px-3 py-2 text-sm font-semibold text-red-600 hover:bg-red-50">Delete</button>
                    </form>
                </div>
            </article>
        @empty
            <div class="rounded-xl border border-dashed border-amber-300 bg-amber-50 px-4 py-5 text-sm text-amber-800">
                No emergency contacts saved yet. Add a trusted person for extra safety.
            </div>
        @endforelse
    </div>

    <p class="mt-4 text-xs text-gray-500"><span x-text="contacts.length"></span> of 3 contacts saved.</p>

    <div x-cloak x-show="showContactModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" x-transition.opacity>
        <div @click.away="showContactModal = false" class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h3 class="text-xl font-bold text-gray-950" x-text="editingContact ? 'Edit Emergency Contact' : 'Add Emergency Contact'"></h3>
                    <p class="mt-1 text-sm text-gray-600">This contact will only be used for safety purposes.</p>
                </div>
                <button type="button" @click="showContactModal = false" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700" aria-label="Close dialog"><x-icons.lucide name="x" class="h-5 w-5" /></button>
            </div>
            <form method="POST" :action="editingContact ? '{{ url('/emergency-contacts') }}/' + editingContact.id : '{{ route('emergency-contacts.store') }}'" class="mt-6 space-y-4">
                @csrf
                <input x-show="editingContact" type="hidden" name="_method" value="PATCH">
                <div><label for="emergency_contact_name" class="mb-1.5 block text-sm font-semibold text-gray-700">Name</label><input id="emergency_contact_name" name="name" type="text" x-model="form.name" required class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-[#2E7D32] focus:ring-[#2E7D32]"><x-input-error :messages="$errors->emergencyContact->get('name')" class="mt-2" /></div>
                <div><label for="emergency_contact_relationship" class="mb-1.5 block text-sm font-semibold text-gray-700">Relationship</label><input id="emergency_contact_relationship" name="relationship" type="text" x-model="form.relationship" placeholder="Parent, spouse, or friend" required class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-[#2E7D32] focus:ring-[#2E7D32]"><x-input-error :messages="$errors->emergencyContact->get('relationship')" class="mt-2" /></div>
                <div><label for="emergency_contact_phone" class="mb-1.5 block text-sm font-semibold text-gray-700">Phone Number</label><input id="emergency_contact_phone" name="phone_number" type="tel" x-model="form.phone_number" placeholder="+60 12-345 6789" required class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-[#2E7D32] focus:ring-[#2E7D32]"><x-input-error :messages="$errors->emergencyContact->get('phone_number')" class="mt-2" /></div>
                <div class="flex justify-end gap-3 pt-2"><button type="button" @click="showContactModal = false" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancel</button><button type="submit" class="rounded-lg bg-[#2E7D32] px-4 py-2 text-sm font-semibold text-white hover:bg-[#256b29]" x-text="editingContact ? 'Save Changes' : 'Add Contact'"></button></div>
            </form>
        </div>
    </div>
</section>
