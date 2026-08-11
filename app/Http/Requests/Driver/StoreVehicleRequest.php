<?php

namespace App\Http\Requests\Driver;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'driver';
    }

    public function rules(): array
    {
        return [
            'plate_number' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9 -]+$/', Rule::unique('vehicles', 'plate_number')],
            'brand' => ['required', 'string', 'max:50'],
            'model' => ['required', 'string', 'max:50'],
            'colour' => ['required', 'string', 'max:20'],
            'seat_capacity' => ['required', 'integer', 'between:1,4'],
            'vehicle_image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'vehicle_image.required' => 'Please upload a vehicle picture.',
            'vehicle_image.image' => 'Vehicle picture must be a JPG, JPEG, PNG or WEBP image.',
            'vehicle_image.mimes' => 'Vehicle picture must be a JPG, JPEG, PNG or WEBP image.',
            'vehicle_image.max' => 'Vehicle picture must not exceed 5 MB.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('plate_number')) {
            $this->merge(['plate_number' => mb_strtoupper(trim($this->string('plate_number')->toString()))]);
        }
    }
}
