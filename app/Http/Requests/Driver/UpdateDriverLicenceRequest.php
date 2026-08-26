<?php

namespace App\Http\Requests\Driver;

use App\Support\DocumentIdentity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateDriverLicenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'driver';
    }

    public function rules(): array
    {
        return [
            'driving_licence' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:8192', 'dimensions:min_width=500,min_height=300'],
            'holder_name' => ['required', 'string', 'max:150'],
            'identity_no' => ['required', 'regex:/^\d{12}$/'],
            'licence_class' => ['nullable', 'string', 'max:20'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['required', 'date', 'after_or_equal:valid_from', 'after_or_equal:today'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'holder_name' => DocumentIdentity::normalizeName($this->input('holder_name')),
            'identity_no' => trim((string) $this->input('identity_no')),
        ]);
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $uploadedLicence = $this->file('driving_licence');
            $scannedHash = $this->session()->get('driver_licence_ocr_hash');

            if ($uploadedLicence && (! $scannedHash || ! hash_equals($scannedHash, hash_file('sha256', $uploadedLicence->getRealPath())))) {
                $validator->errors()->add('driving_licence', 'Scan the newly selected driving licence before saving.');
            }

            if (DocumentIdentity::normalizeName($this->input('holder_name')) !== DocumentIdentity::normalizeName($this->user()?->name)) {
                $validator->errors()->add('holder_name', 'Driving licence holder name must match your driver profile name.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'identity_no.regex' => 'Identity number must contain exactly 12 digits.',
            'valid_until.after_or_equal' => 'The driving licence has expired. Upload a renewed licence.',
        ];
    }
}
