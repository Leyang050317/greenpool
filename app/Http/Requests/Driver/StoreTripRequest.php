<?php

namespace App\Http\Requests\Driver;

use App\Models\Trip;
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
            'departure_place_id' => ['required', 'string', 'max:255'],
            'destination_place_id' => ['required', 'string', 'max:255'],
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
            $licence = $this->user()->driverLicence()->first();

            if (! $licence) {
                $validator->errors()->add('driver_licence', 'Upload your driving licence in My Profile before creating a trip.');
            } elseif (! $licence->isValidOn(now())) {
                $validator->errors()->add('driver_licence', 'Renew and verify your driving licence before creating or updating a trip.');
            }

            $vehicle = Vehicle::find($this->integer('vehicle_id'));

            if ($vehicle && $vehicle->user_id !== $this->user()->id) {
                $validator->errors()->add('vehicle_id', 'Please select one of your vehicles.');
            }

            if ($vehicle && $this->integer('available_seats') > $vehicle->seat_capacity) {
                $validator->errors()->add('available_seats', 'Available seats cannot exceed the vehicle capacity.');
            }

            if ($this->filled('departure_date') && $this->filled('departure_time') && now()->greaterThan(Carbon::parse($this->input('departure_date').' '.$this->input('departure_time')))) {
                $validator->errors()->add('departure_time', 'Departure date and time cannot be in the past.');
            }

            if ($this->filled('departure_date') && $this->filled('departure_time') && ! $validator->errors()->has('departure_time')) {
                $departureAt = Carbon::parse($this->input('departure_date').' '.$this->input('departure_time'));

                if ($licence && $departureAt->copy()->startOfDay()->gt($licence->valid_until->copy()->startOfDay())) {
                    $validator->errors()->add(
                        'departure_date',
                        'Trip departure cannot be scheduled after your driving licence expires on '.$licence->valid_until->format('d M Y').'.'
                    );
                }

                $trip = $this->route('trip');
                $conflicts = Trip::query()
                    ->where('user_id', $this->user()->id)
                    ->where('status', 'Scheduled')
                    ->where('departure_at', $departureAt)
                    ->when($trip instanceof Trip, fn ($query) => $query->whereKeyNot($trip->getKey()))
                    ->exists();

                if ($conflicts) {
                    $validator->errors()->add('departure_time', 'You already have a scheduled trip at this departure date and time.');
                }
            }
        }];
    }

    public function tripData(): array
    {
        return [
            ...$this->safe()->except(['departure_date', 'departure_time', 'departure_place_id', 'destination_place_id']),
            'departure_at' => Carbon::parse($this->input('departure_date').' '.$this->input('departure_time')),
        ];
    }
}
