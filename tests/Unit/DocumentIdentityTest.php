<?php

namespace Tests\Unit;

use App\Support\DocumentIdentity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DocumentIdentityTest extends TestCase
{
    #[DataProvider('equivalentNames')]
    public function test_name_normalization_is_deterministic(string $input, string $expected): void
    {
        $this->assertSame($expected, DocumentIdentity::normalizeName($input));
    }

    public static function equivalentNames(): array
    {
        return [
            ['ER KIM WEN', 'ER KIM WEN'],
            ['er kim wen', 'ER KIM WEN'],
            ['  ER   KIM  WEN  ', 'ER KIM WEN'],
        ];
    }

    public function test_meaningfully_different_names_remain_different(): void
    {
        $this->assertNotSame(
            DocumentIdentity::normalizeName('ER KIM WEN'),
            DocumentIdentity::normalizeName('ER KIM WEI'),
        );
    }
}
