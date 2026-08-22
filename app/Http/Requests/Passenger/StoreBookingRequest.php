<?php

namespace App\Http\Requests\Passenger;

use App\Models\Booking;
use App\Models\Trip;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'passenger';
    }

    public function rules(): array
    {
        return [
            'trip_id' => ['required', 'integer', 'exists:trips,trip_id'],
            'pickup_point' => ['required', 'string', 'max:255'],
            'pickup_place_id' => ['required', 'string', 'max:255'],
            'number_of_seats' => ['required', 'integer', 'min:1'],
            'number_of_luggage' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $trip = Trip::find($this->integer('trip_id'));

            if (! $trip) {
                return;
            }

            if ($trip->status !== 'Scheduled' || $trip->departure_at->isPast()) {
                $validator->errors()->add('trip_id', 'This trip is no longer available for booking.');
            }

            if ($trip->user_id === $this->user()->id) {
                $validator->errors()->add('trip_id', 'You cannot book your own trip.');
            }

            if ($this->integer('number_of_seats') > $trip->available_seats) {
                $validator->errors()->add('number_of_seats', 'Requested seats must not exceed the available seats.');
            }

            if (Booking::where('trip_id', $trip->trip_id)
                ->where('passenger_id', $this->user()->id)
                ->where('booking_status', '!=', 'Rejected')
                ->exists()) {
                $validator->errors()->add('trip_id', 'You already submitted a booking request for this trip.');
            }
        }];
    }

    public function bookingData(): array
    {
        return [
            'trip_id' => $this->integer('trip_id'),
            'passenger_id' => $this->user()->id,
            'booking_status' => 'Pending',
            'number_of_seats' => $this->integer('number_of_seats'),
            'number_of_luggage' => $this->integer('number_of_luggage'),
            'pickup_point' => $this->string('pickup_point')->trim()->toString(),
        ];
    }
}
