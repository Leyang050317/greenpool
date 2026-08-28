<?php

namespace App\Http\Requests\Driver;

use App\Models\Vehicle;
use App\Services\Vehicle\VehicleImageValidationService;
use App\Support\DocumentIdentity;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateVehicleRequest extends StoreVehicleRequest
{
    public function authorize(): bool
    {
        $vehicle = $this->route('vehicle');

        return $this->user()?->role === 'driver'
            && $vehicle instanceof Vehicle
            && $vehicle->user_id === $this->user()->id;
    }

    public function rules(): array
    {
        /** @var Vehicle $vehicle */
        $vehicle = $this->route('vehicle');

        $photosRequired = $this->photosRequired($vehicle);
        $geranRequired = $this->geranRequired($vehicle);
        $requiredPhoto = Rule::requiredIf($photosRequired);
        $requiredGeran = Rule::requiredIf($geranRequired);
        $requiredToken = Rule::requiredIf($photosRequired && config('vehicle_vision.enabled'));

        return [
            'plate_number' => [
                'required',
                'string',
                'max:20',
                'regex:/^[A-Za-z0-9 -]+$/',
                Rule::unique('vehicles', 'plate_number')->ignore($vehicle->vehicle_id, 'vehicle_id'),
            ],
            'brand' => ['required', 'string', 'max:50'],
            'model' => ['required', 'string', 'max:50'],
            'colour' => ['required', 'string', 'max:20'],
            'seat_capacity' => ['required', 'integer', 'between:1,4'],
            'front_image' => [$requiredPhoto, 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192', 'dimensions:min_width=800,min_height=450'],
            'rear_image' => [$requiredPhoto, 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192', 'dimensions:min_width=800,min_height=450'],
            'side_image' => [$requiredPhoto, 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192', 'dimensions:min_width=800,min_height=450'],
            'front_image_validation_token' => [$requiredToken, 'nullable', 'string'],
            'rear_image_validation_token' => [$requiredToken, 'nullable', 'string'],
            'side_image_validation_token' => [$requiredToken, 'nullable', 'string'],
            'vehicle_geran' => [$requiredGeran, 'nullable', 'image', 'mimes:jpg,jpeg,png', 'max:8192', 'dimensions:min_width=500,min_height=300'],
            'registered_owner_name' => [$requiredGeran, 'nullable', 'string', 'max:150'],
            'owner_identity_no' => [$requiredGeran, 'nullable', 'regex:/^\d{12}$/'],
            'manufacturer' => [$requiredGeran, 'nullable', 'string', 'max:50'],
            'model_name' => [$requiredGeran, 'nullable', 'string', 'max:100'],
            'geran_plate_number' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            /** @var Vehicle $vehicle */
            $vehicle = $this->route('vehicle');
            if ($this->fieldChanged($vehicle, 'brand')) {
                $validator->errors()->add('brand', 'Vehicle brand cannot be updated.');
            }
            $changedIdentityFields = collect(['plate_number', 'model', 'colour'])
                ->filter(fn (string $field) => $this->fieldChanged($vehicle, $field));
            if ($changedIdentityFields->count() > 1) {
                $changedIdentityFields->each(fn (string $field) => $validator->errors()->add($field, 'Update only one of the plate number, model, or colour at a time.'));

                return;
            }
            if (! $this->identityChanged($vehicle)) {
                return;
            }

            if ($this->photosRequired($vehicle) && config('vehicle_vision.enabled')) {
                $service = app(VehicleImageValidationService::class);
                $photoContexts = [];
                foreach (['front_image' => 'FRONT', 'rear_image' => 'REAR', 'side_image' => 'SIDE'] as $field => $view) {
                    if (! $this->hasFile($field)) {
                        continue;
                    }
                    $context = $service->tokenPayload((string) $this->input($field.'_validation_token'), $this->file($field), $view);
                    if ($context === null) {
                        $validator->errors()->add($field, "Validate the {$view} vehicle photo again before saving.");
                    } else {
                        $photoContexts[] = $context;
                    }
                }
                if (collect($photoContexts)->pluck('colour')->filter()->unique()->count() > 1) {
                    $validator->errors()->add('colour', 'The vehicle colour does not match across the uploaded photos.');
                }

                $colourCandidates = collect($photoContexts)
                    ->filter(fn (array $context) => filled($context['detected_colour'] ?? null));
                $winningGroup = $colourCandidates
                    ->groupBy(fn (array $context) => $context['colour_group'] ?? $context['detected_colour'])
                    ->map(fn ($group) => $group->sum(fn (array $context) => (float) ($context['colour_confidence'] ?? 0)))
                    ->sortDesc()
                    ->keys()
                    ->first();
                $detectedColour = $colourCandidates
                    ->filter(fn (array $context) => ($context['colour_group'] ?? $context['detected_colour']) === $winningGroup)
                    ->sortByDesc(fn (array $context) => (float) ($context['colour_confidence'] ?? 0))
                    ->first()['detected_colour'] ?? null;
                if ($detectedColour && mb_strtoupper(trim((string) $this->input('colour'))) !== mb_strtoupper(trim((string) $detectedColour))) {
                    $validator->errors()->add('colour', 'Vehicle colour must match the colour detected from the uploaded photos.');
                }

                $detectedPlates = collect($photoContexts)->pluck('plate_number')->filter()->map(fn ($plate) => preg_replace('/[^A-Z0-9]/', '', mb_strtoupper($plate)))->unique();
                $submittedPlate = preg_replace('/[^A-Z0-9]/', '', mb_strtoupper((string) $this->input('plate_number')));
                if ($detectedPlates->isEmpty()) {
                    $validator->errors()->add('plate_number', 'At least one uploaded vehicle photo must show a readable plate number.');
                } elseif ($detectedPlates->count() > 1 || $detectedPlates->contains(fn ($plate) => $plate !== $submittedPlate)) {
                    $validator->errors()->add('plate_number', 'All detected plate numbers must match the registered vehicle plate number.');
                }
            }

            if ($this->geranRequired($vehicle)) {
                $scan = $this->session()->get('vehicle_geran_ocr');
                $geranHash = $this->file('vehicle_geran') ? hash_file('sha256', $this->file('vehicle_geran')->getRealPath()) : null;
                if (! is_array($scan) || ! $geranHash || ! hash_equals((string) ($scan['hash'] ?? ''), $geranHash)) {
                    $validator->errors()->add('vehicle_geran', 'Scan the newly selected Vehicle Geran/VOC before saving.');
                } else {
                    foreach (['registered_owner_name', 'owner_identity_no', 'manufacturer', 'model_name'] as $field) {
                        if (DocumentIdentity::normalizeName($this->input($field)) !== DocumentIdentity::normalizeName($scan['fields'][$field] ?? null)) {
                            $validator->errors()->add($field, 'Vehicle Geran details must use the values extracted by the document scan.');
                        }
                    }

                    $submittedGeranPlate = preg_replace('/[^A-Z0-9]/', '', mb_strtoupper((string) $this->input('geran_plate_number')));
                    $scannedGeranPlate = preg_replace('/[^A-Z0-9]/', '', mb_strtoupper((string) ($scan['fields']['registration_no'] ?? '')));
                    if ($submittedGeranPlate === '' || $scannedGeranPlate === '' || $submittedGeranPlate !== $scannedGeranPlate) {
                        $validator->errors()->add('geran_plate_number', 'Vehicle Geran registration number must use the value extracted by the document scan.');
                    }
                }

                if (DocumentIdentity::normalizeName($this->input('registered_owner_name')) !== DocumentIdentity::normalizeName($this->user()?->name)) {
                    $validator->errors()->add('registered_owner_name', 'Geran registered owner name must match your driver profile name.');
                }

                if (mb_strtoupper(trim((string) $this->input('manufacturer'))) !== mb_strtoupper(trim((string) $vehicle->brand))) {
                    $validator->errors()->add('manufacturer', 'Vehicle Geran manufacturer must match the registered vehicle brand.');
                }
                if (mb_strtoupper(trim((string) $this->input('model_name'))) !== mb_strtoupper(trim((string) $this->input('model')))) {
                    $validator->errors()->add('model', 'Vehicle model must match the model extracted from the Geran.');
                }
                $geranPlate = preg_replace('/[^A-Z0-9]/', '', mb_strtoupper((string) $this->input('geran_plate_number')));
                $submittedPlate = preg_replace('/[^A-Z0-9]/', '', mb_strtoupper((string) $this->input('plate_number')));
                if ($geranPlate === '' || $geranPlate !== $submittedPlate) {
                    $validator->errors()->add('plate_number', 'Plate number must match the registration number extracted from the Vehicle Geran/VOC.');
                }
            }
        }];
    }

    private function identityChanged(Vehicle $vehicle): bool
    {
        return collect(['plate_number', 'brand', 'model', 'colour'])
            ->contains(fn (string $field) => mb_strtoupper(trim((string) $this->input($field))) !== mb_strtoupper(trim((string) $vehicle->{$field})));
    }

    private function photosRequired(Vehicle $vehicle): bool
    {
        return collect(['plate_number', 'colour'])
            ->contains(fn (string $field) => mb_strtoupper(trim((string) $this->input($field))) !== mb_strtoupper(trim((string) $vehicle->{$field})));
    }

    private function geranRequired(Vehicle $vehicle): bool
    {
        return collect(['plate_number', 'model'])
            ->contains(fn (string $field) => mb_strtoupper(trim((string) $this->input($field))) !== mb_strtoupper(trim((string) $vehicle->{$field})));
    }

    private function fieldChanged(Vehicle $vehicle, string $field): bool
    {
        return mb_strtoupper(trim((string) $this->input($field))) !== mb_strtoupper(trim((string) $vehicle->{$field}));
    }
}
