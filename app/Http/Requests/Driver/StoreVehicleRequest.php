<?php

namespace App\Http\Requests\Driver;

use App\Support\DocumentIdentity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'driver';
    }

    public function rules(): array
    {
        $documentFlow = collect(['front_image', 'rear_image', 'side_image', 'vehicle_geran', 'driving_licence'])
            ->contains(fn (string $field) => $this->hasFile($field));
        $requiredForDocuments = Rule::requiredIf($documentFlow);

        return [
            'plate_number' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9 -]+$/', Rule::unique('vehicles', 'plate_number')],
            'brand' => ['required', 'string', 'max:50'],
            'model' => ['required', 'string', 'max:50'],
            'colour' => ['required', 'string', 'max:20'],
            'seat_capacity' => ['required', 'integer', 'between:1,4'],
            'vehicle_image' => ['required_without_all:front_image,rear_image,side_image', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'front_image' => $this->vehicleImageRules($requiredForDocuments),
            'rear_image' => $this->vehicleImageRules($requiredForDocuments),
            'side_image' => $this->vehicleImageRules($requiredForDocuments),
            'vehicle_geran' => $this->documentImageRules($requiredForDocuments),
            'driving_licence' => $this->documentImageRules($requiredForDocuments),
            'voc_reference_no' => ['nullable', 'string', 'max:30', 'regex:/^[A-Z0-9]+$/'],
            'registered_owner_name' => [$requiredForDocuments, 'nullable', 'string', 'max:150'],
            'owner_identity_no' => [$requiredForDocuments, 'nullable', 'regex:/^\d{12}$/'],
            'owner_address' => ['nullable', 'string', 'max:1000'],
            'chassis_no' => ['nullable', 'string', 'max:40', 'regex:/^[A-HJ-NPR-Z0-9]+$/'],
            'engine_no' => ['nullable', 'string', 'max:40', 'regex:/^[A-Z0-9]+$/'],
            'manufacturer' => ['nullable', 'string', 'max:50'],
            'model_name' => ['nullable', 'string', 'max:100'],
            'engine_capacity' => ['nullable', 'integer', 'min:1'],
            'fuel_type' => ['nullable', 'string', 'max:30'],
            'origin_status' => ['nullable', 'string', 'max:100'],
            'usage_class' => ['nullable', 'string', 'max:100'],
            'body_type' => ['nullable', 'string', 'max:100'],
            'manufacturing_year' => ['nullable', 'regex:/^\d{4}$/'],
            'registration_date' => ['nullable', 'date'],
            'bdm' => ['nullable', 'integer', 'min:1'],
            'bgk' => ['nullable', 'integer', 'min:1'],
            'btm' => ['nullable', 'integer', 'min:1'],
            'registration_condition_1' => ['nullable', 'string', 'max:255'],
            'registration_condition_2' => ['nullable', 'string', 'max:255'],
            'registration_condition_3' => ['nullable', 'string', 'max:255'],
            'licence_name' => [$requiredForDocuments, 'nullable', 'string', 'max:150'],
            'licence_identity_no' => [$requiredForDocuments, 'nullable', 'regex:/^\d{12}$/'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'nationality' => ['nullable', 'string', 'max:50'],
            'licence_class' => ['nullable', 'string', 'max:20'],
            'licence_valid_from' => ['nullable', 'date'],
            'licence_valid_until' => ['nullable', 'date', 'after_or_equal:licence_valid_from'],
            'licence_address' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            '*.required' => 'This field is required.',
            'owner_identity_no.regex' => 'Identity number must contain exactly 12 digits.',
            'licence_identity_no.regex' => 'Identity number must contain exactly 12 digits.',
            'licence_valid_until.after_or_equal' => 'Licence expiry date must be on or after the licence start date.',
            'front_image.dimensions' => 'Vehicle photos must be at least 800 × 450 pixels.',
            'rear_image.dimensions' => 'Vehicle photos must be at least 800 × 450 pixels.',
            'side_image.dimensions' => 'Vehicle photos must be at least 800 × 450 pixels.',
            'vehicle_geran.dimensions' => 'Vehicle Geran image must be at least 500 × 300 pixels.',
            'driving_licence.dimensions' => 'Driving licence image must be at least 500 × 300 pixels.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('plate_number')) {
            $this->merge(['plate_number' => mb_strtoupper(trim($this->string('plate_number')->toString()))]);
        }
        $upper = ['voc_reference_no', 'chassis_no', 'engine_no', 'manufacturer', 'model_name', 'fuel_type', 'origin_status', 'usage_class', 'body_type'];
        $values = [];
        foreach ($upper as $field) {
            if ($this->filled($field)) {
                $values[$field] = mb_strtoupper(trim($this->string($field)->toString()));
            }
        }
        $values['registered_owner_name'] = DocumentIdentity::normalizeName($this->input('registered_owner_name'));
        $values['licence_name'] = DocumentIdentity::normalizeName($this->input('licence_name'));
        $values['owner_identity_no'] = trim((string) $this->input('owner_identity_no'));
        $values['licence_identity_no'] = trim((string) $this->input('licence_identity_no'));
        $this->merge($values);
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->hasFile('vehicle_geran') && ! $this->hasFile('driving_licence')) {
                return;
            }
            if (DocumentIdentity::normalizeName($this->input('registered_owner_name')) !== DocumentIdentity::normalizeName($this->input('licence_name'))) {
                $validator->errors()->add('registered_owner_name', 'Registered owner name does not match the driving licence holder name.');
            }
            if (trim((string) $this->input('owner_identity_no')) !== trim((string) $this->input('licence_identity_no'))) {
                $validator->errors()->add('owner_identity_no', 'Registered owner identity number does not match the driving licence identity number.');
            }
        }];
    }

    private function vehicleImageRules(mixed $required): array
    {
        return [$required, 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192', 'dimensions:min_width=800,min_height=450'];
    }

    private function documentImageRules(mixed $required): array
    {
        return [$required, 'nullable', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:8192', 'dimensions:min_width=500,min_height=300'];
    }
}
