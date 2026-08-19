<?php

namespace App\Http\Requests\Driver;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDriverPreferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'driver';
    }

    public function rules(): array
    {
        return [
            'smoking_allowed' => ['required', 'boolean'],
            'pets_allowed' => ['required', 'boolean'],
            'conversation_preference' => ['required', Rule::in(['Quiet', 'Moderate', 'Chatty'])],
        ];
    }
}
