<?php

namespace Tests\Unit;

use App\Services\Ocr\DocumentValidationService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DocumentValidationServiceTest extends TestCase
{
    #[DataProvider('identityNumbers')]
    public function test_identity_number_is_exactly_twelve_digits(string $value, bool $valid): void
    {
        $this->assertSame($valid, (new DocumentValidationService)->identityNumber($value));
    }

    public static function identityNumbers(): array
    {
        return [['991109040290', true], ['99110904029', false], ['9911090402901', false], ['99110904029A', false], ['991109-04-0290', false]];
    }

    public function test_dates_and_licence_period_are_strict(): void
    {
        $validator = new DocumentValidationService;
        $this->assertTrue($validator->date('09/11/1999', false));
        $this->assertFalse($validator->date('31/02/2025'));
        $this->assertFalse($validator->licencePeriod('17/03/2027', '11/03/2026'));
        $this->assertTrue($validator->chassisNumber('PL1BT3SRRSB407045'));
    }
}
