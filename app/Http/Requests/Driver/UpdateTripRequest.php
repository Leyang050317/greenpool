<?php

namespace App\Http\Requests\Driver;

use App\Models\Trip;
use Carbon\Carbon;
use Illuminate\Validation\Validator;

class UpdateTripRequest extends StoreTripRequest
{
    public function rules(): array
    {
        if ($this->isExpiredRecovery()) {
            return [
                'departure_date' => ['required', 'date'],
                'departure_time' => ['required', 'date_format:H:i'],
            ];
        }

        return [
            ...parent::rules(),
            'departure_place_id' => ['nullable', 'string', 'max:255'],
            'destination_place_id' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        if (! $this->isExpiredRecovery()) {
            return parent::after();
        }

        return [function (Validator $validator): void {
            if (! $this->filled('departure_date') || ! $this->filled('departure_time')) {
                return;
            }

            if (! Carbon::parse($this->input('departure_date').' '.$this->input('departure_time'))->isFuture()) {
                $validator->errors()->add('departure_time', 'Choose a future departure date and time to recover this trip.');
            }
        }];
    }

    public function tripData(): array
    {
        if ($this->isExpiredRecovery()) {
            return ['departure_at' => Carbon::parse($this->input('departure_date').' '.$this->input('departure_time'))];
        }

        return parent::tripData();
    }

    private function isExpiredRecovery(): bool
    {
        $trip = $this->route('trip');

        return $trip instanceof Trip && $trip->status === 'Scheduled' && $trip->hasExpiredDeparture();
    }

    public function authorize(): bool
    {
        $trip = $this->route('trip');

        return parent::authorize()
            && $trip instanceof Trip
            && $trip->user_id === $this->user()->id
            && $trip->status === 'Scheduled';
    }
}
