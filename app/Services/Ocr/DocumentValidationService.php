<?php

namespace App\Services\Ocr;

use Carbon\CarbonImmutable;
use Throwable;

final class DocumentValidationService
{
    public function identityNumber(?string $value): bool
    {
        return is_string($value) && preg_match('/^\d{12}$/D', $value) === 1;
    }

    public function date(?string $value, bool $allowFuture = true): bool
    {
        if (! is_string($value)) {
            return false;
        }
        try {
            $date = CarbonImmutable::createFromFormat('d/m/Y', $value);
        } catch (Throwable) {
            return false;
        }

        return $date !== null
            && $date->format('d/m/Y') === $value
            && ($allowFuture || ! $date->isFuture());
    }

    public function licencePeriod(string $from, string $until): bool
    {
        if (! $this->date($from) || ! $this->date($until)) {
            return false;
        }

        return CarbonImmutable::createFromFormat('d/m/Y', $until)
            ->greaterThanOrEqualTo(CarbonImmutable::createFromFormat('d/m/Y', $from));
    }

    public function chassisNumber(?string $value): bool
    {
        if (! is_string($value) || $value === '') {
            return false;
        }

        return strlen($value) !== 17 || preg_match('/^[A-HJ-NPR-Z0-9]{17}$/D', $value) === 1;
    }
}
