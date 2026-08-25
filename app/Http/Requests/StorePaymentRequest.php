<?php

namespace App\Http\Requests;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->id === $this->route('payment')?->payer_id;
    }

    public function rules(): array
    {
        return [
            'payment_method' => ['required', Rule::in(array_keys(Payment::METHODS))],
        ];
    }
}
