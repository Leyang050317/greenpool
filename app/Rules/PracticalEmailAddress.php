<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PracticalEmailAddress implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! str_contains($value, '@')) {
            $fail('Please enter a valid email address.');

            return;
        }

        [$localPart] = explode('@', $value, 2);
        $hasPracticalLocalPart = preg_match('/^[A-Za-z0-9](?:[A-Za-z0-9._+\-]*[A-Za-z0-9])?$/D', $localPart) === 1
            && ! str_contains($localPart, '..');

        if (! $hasPracticalLocalPart) {
            $fail('Please enter an email address that starts and ends with a letter or number.');
        }
    }
}
