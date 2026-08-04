<?php

namespace App\Http\Requests\Driver;

use App\Models\Trip;

class UpdateTripRequest extends StoreTripRequest
{
    public function authorize(): bool
    {
        $trip = $this->route('trip');

        return parent::authorize()
            && $trip instanceof Trip
            && $trip->user_id === $this->user()->id
            && $trip->status === 'Scheduled';
    }
}
