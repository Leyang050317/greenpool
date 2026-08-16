<?php

namespace App\Http\Requests\Driver;

use App\Models\Vehicle;
use Illuminate\Validation\Rule;

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
            'vehicle_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function after(): array
    {
        return [];
    }
}
