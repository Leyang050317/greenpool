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
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('plate_number')) {
            $this->merge(['plate_number' => mb_strtoupper(trim($this->string('plate_number')->toString()))]);
        }
    }
}
