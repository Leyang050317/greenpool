<?php

namespace Tests\Unit;

use App\Services\Vehicle\DocumentMatchingService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DocumentMatchingServiceTest extends TestCase
{
    #[DataProvider('matchingCases')]
    public function test_both_exact_identity_rules_are_mandatory(string $licenceName, string $licenceIc, string $ownerName, string $ownerIc, string $status): void
    {
        $result = (new DocumentMatchingService)->match(
            ['name' => $licenceName, 'identity_no' => $licenceIc],
            ['registered_owner_name' => $ownerName, 'owner_identity_no' => $ownerIc],
        );

        $this->assertSame($status, $result['status']);
    }

    public static function matchingCases(): array
    {
        return [
            ['ER KIM WEN', '991109040290', 'ER KIM WEN', '991109040290', 'Verified'],
            ['er kim wen', '991109040290', 'ER KIM WEN', '991109040290', 'Verified'],
            ['ER  KIM   WEN', '991109040290', 'ER KIM WEN', '991109040290', 'Verified'],
            ['ER KIM WEN', '991109040290', 'ER KIM WEI', '991109040290', 'Rejected'],
            ['ER KIM WEN', '991109040290', 'ER KIM WEN', '991109040291', 'Rejected'],
            ['ER KIM WEN', '991109040290', 'JOHN TAN', '900101010101', 'Rejected'],
        ];
    }
}
