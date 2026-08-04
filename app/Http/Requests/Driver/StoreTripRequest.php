<?php

namespace App\Http\Requests\Driver;

use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'driver';
    }

    public function rules(): array
    {
        return [
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,vehicle_id'],
            'departure_location' => ['required', 'string', 'max:255'],
            'destination' => ['required', 'string', 'max:255'],
            'departure_date' => ['required', 'date', 'after_or_equal:today'],
            'departure_time' => ['required', 'date_format:H:i'],
            'available_seats' => ['required', 'integer', 'between:1,4'],
            'price_per_passenger' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $vehicle = Vehicle::find($this->integer('vehicle_id'));

            if ($vehicle && ($vehicle->user_id !== $this->user()->id || $vehicle->status !== 'Active')) {
                $validator->errors()->add('vehicle_id', 'Please select one of your active vehicles.');
            }

            if ($vehicle && $this->integer('available_seats') > $vehicle->seat_capacity) {
                $validator->errors()->add('available_seats', 'Available seats cannot exceed the vehicle capacity.');
            }

            if (mb_strtolower(trim((string) $this->input('departure_location'))) === mb_strtolower(trim((string) $this->input('destination')))) {
                $validator->errors()->add('destination', 'Departure location and destination cannot be the same.');
            }

            if ($this->filled('departure_date') && $this->filled('departure_time') && now()->greaterThan(Carbon::parse($this->input('departure_date').' '.$this->input('departure_time')))) {
                $validator->errors()->add('departure_time', 'Departure date and time cannot be in the past.');
            }
        }];
    }

    public function tripData(): array
    {
        return [
            ...$this->safe()->except(['departure_date', 'departure_time']),
            'departure_at' => Carbon::parse($this->input('departure_date').' '.$this->input('departure_time')),
        ];
    }
}
