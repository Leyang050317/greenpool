<?php

namespace App\Http\Requests\Driver;

use App\Services\Vehicle\VehicleImageValidationService;
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
        $documentFlow = collect(['front_image', 'rear_image', 'side_image', 'vehicle_geran'])
            ->contains(fn (string $field) => $this->hasFile($field));
        $requiredForDocuments = Rule::requiredIf($documentFlow);
        $requiredAiToken = Rule::requiredIf($documentFlow && config('vehicle_vision.enabled'));

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
            'front_image_validation_token' => [$requiredAiToken, 'nullable', 'string'],
            'rear_image_validation_token' => [$requiredAiToken, 'nullable', 'string'],
            'side_image_validation_token' => [$requiredAiToken, 'nullable', 'string'],
            'vehicle_geran' => $this->documentImageRules($requiredForDocuments),
            'geran_plate_number' => [$requiredForDocuments, 'nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9 -]+$/'],
            'voc_reference_no' => ['nullable', 'string', 'max:30', 'regex:/^[A-Z0-9]+$/'],
            'registered_owner_name' => [$requiredForDocuments, 'nullable', 'string', 'max:150'],
            'owner_identity_no' => [$requiredForDocuments, 'nullable', 'regex:/^\d{12}$/'],
            'owner_address' => ['nullable', 'string', 'max:1000'],
            'chassis_no' => ['nullable', 'string', 'max:40', 'regex:/^[A-HJ-NPR-Z0-9]+$/'],
            'engine_no' => ['nullable', 'string', 'max:40', 'regex:/^[A-Z0-9]+$/'],
            'manufacturer' => [$requiredForDocuments, 'nullable', 'string', 'max:50'],
            'model_name' => [$requiredForDocuments, 'nullable', 'string', 'max:100'],
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
        ];
    }

    public function messages(): array
    {
        return [
            '*.required' => 'This field is required.',
            'owner_identity_no.regex' => 'Identity number must contain exactly 12 digits.',
            'front_image.dimensions' => 'Vehicle photos must be at least 800 × 450 pixels.',
            'rear_image.dimensions' => 'Vehicle photos must be at least 800 × 450 pixels.',
            'side_image.dimensions' => 'Vehicle photos must be at least 800 × 450 pixels.',
            'vehicle_geran.dimensions' => 'Vehicle Geran image must be at least 500 × 300 pixels.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('plate_number')) {
            $this->merge(['plate_number' => mb_strtoupper(trim($this->string('plate_number')->toString()))]);
        }
        if ($this->hasFile('vehicle_geran')) {
            $this->merge([
                'brand' => trim((string) $this->input('manufacturer')),
                'model' => trim((string) $this->input('model_name')),
            ]);
        }
        $upper = ['voc_reference_no', 'chassis_no', 'engine_no', 'manufacturer', 'model_name', 'fuel_type', 'origin_status', 'usage_class', 'body_type'];
        $values = [];
        foreach ($upper as $field) {
            if ($this->filled($field)) {
                $values[$field] = mb_strtoupper(trim($this->string($field)->toString()));
            }
        }
        $values['registered_owner_name'] = DocumentIdentity::normalizeName($this->input('registered_owner_name'));
        $values['owner_identity_no'] = trim((string) $this->input('owner_identity_no'));
        $this->merge($values);
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! ($this->user()->driverLicence?->isValidOn(now()) ?? false)) {
                $validator->errors()->add('driver_licence', 'Upload and verify a valid driving licence in My Profile before adding a vehicle.');
            }

            if (config('vehicle_vision.enabled')) {
                $service = app(VehicleImageValidationService::class);
                $photoContexts = [];
                foreach (['front_image' => 'FRONT', 'rear_image' => 'REAR', 'side_image' => 'SIDE'] as $field => $view) {
                    if (! $this->hasFile($field)) {
                        continue;
                    }
                    $context = $service->tokenPayload((string) $this->input($field.'_validation_token'), $this->file($field), $view);
                    if ($context === null) {
                        $validator->errors()->add($field, "Validate the {$view} vehicle photo again before continuing.");
                    } else {
                        $photoContexts[$field] = $context;
                    }
                }
                $colours = collect($photoContexts)->pluck('colour')->filter()->unique()->values();
                if ($colours->count() > 1) {
                    foreach (array_keys($photoContexts) as $field) {
                        $validator->errors()->add($field, 'The vehicle colour does not match across the front, rear, and side photos. Upload photos of the same vehicle.');
                    }
                }

                $submittedPlate = $this->canonicalPlate($this->input('plate_number'));
                foreach ($photoContexts as $field => $context) {
                    $detectedPlate = $this->canonicalPlate($context['plate_number'] ?? null);
                    if ($detectedPlate !== '' && $submittedPlate !== '' && $detectedPlate !== $submittedPlate) {
                        $validator->errors()->add($field, 'The detected vehicle photo plate number does not match the submitted plate number.');
                    }
                }
            }

            if (! $this->hasFile('vehicle_geran')) {
                return;
            }
            $submittedPlate = $this->canonicalPlate($this->input('plate_number'));
            $geranPlate = $this->canonicalPlate($this->input('geran_plate_number'));
            if ($submittedPlate === '' || $geranPlate === '' || $submittedPlate !== $geranPlate) {
                $validator->errors()->add('geran_plate_number', 'The plate number from the vehicle photos must match the registration number extracted from the Vehicle Geran/VOC.');
            }

            $accountName = DocumentIdentity::normalizeName($this->user()?->name);
            if (DocumentIdentity::normalizeName($this->input('registered_owner_name')) !== $accountName) {
                $validator->errors()->add('registered_owner_name', 'Geran registered owner name does not match your driver account name. Update your profile to your legal name or upload the correct Geran.');
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

    private function canonicalPlate(mixed $value): string
    {
        return preg_replace('/[^A-Z0-9]/', '', mb_strtoupper((string) $value)) ?? '';
    }
}
