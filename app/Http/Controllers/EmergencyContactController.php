<?php

namespace App\Http\Controllers;

use App\Models\EmergencyContact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EmergencyContactController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->emergencyContacts()->count() >= 3) {
            throw ValidationException::withMessages([
                'emergency_contacts' => 'You can save a maximum of three emergency contacts.',
            ])->errorBag('emergencyContact');
        }

        $user->emergencyContacts()->create($this->validatedData($request));

        return redirect()->route($this->profileRoute($user))->with('status', 'emergency-contact-created');
    }

    public function update(Request $request, EmergencyContact $emergencyContact): RedirectResponse
    {
        $this->ensureOwnedByCurrentUser($request, $emergencyContact);

        $emergencyContact->update($this->validatedData($request));

        return redirect()->route($this->profileRoute($request->user()))->with('status', 'emergency-contact-updated');
    }

    public function destroy(Request $request, EmergencyContact $emergencyContact): RedirectResponse
    {
        $this->ensureOwnedByCurrentUser($request, $emergencyContact);

        $emergencyContact->delete();

        return redirect()->route($this->profileRoute($request->user()))->with('status', 'emergency-contact-deleted');
    }

    private function validatedData(Request $request): array
    {
        return $request->validateWithBag('emergencyContact', [
            'name' => ['required', 'string', 'max:255'],
            'relationship' => ['required', 'string', 'max:100'],
            'phone_number' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9\s\-()]{7,30}$/'],
        ]);
    }

    private function ensureOwnedByCurrentUser(Request $request, EmergencyContact $emergencyContact): void
    {
        abort_unless($emergencyContact->user_id === $request->user()->id, 403);
    }

    private function profileRoute($user): string
    {
        return $user->role === 'driver' ? 'driver.profile.edit' : 'profile.edit';
    }
}
