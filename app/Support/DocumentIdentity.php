<?php

namespace App\Support;

final class DocumentIdentity
{
    public static function normalizeName(?string $name): string
    {
        return mb_strtoupper(preg_replace('/\s+/u', ' ', trim((string) $name)) ?? '');
    }
}
