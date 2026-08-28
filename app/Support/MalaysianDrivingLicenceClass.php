<?php

namespace App\Support;

class MalaysianDrivingLicenceClass
{
    private const CLASSES = ['B2', 'A1', 'DA', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'];

    public static function parse(mixed $value): array
    {
        $remaining = preg_replace('/[^A-Z0-9]/', '', mb_strtoupper((string) $value)) ?? '';
        $classes = [];

        while ($remaining !== '') {
            $matched = collect(self::CLASSES)->first(fn (string $class) => str_starts_with($remaining, $class));
            if ($matched === null) {
                $remaining = substr($remaining, 1);
                continue;
            }

            $classes[] = $matched;
            $remaining = substr($remaining, strlen($matched));
        }

        return array_values(array_unique($classes));
    }

    public static function normalize(mixed $value): string
    {
        return implode(', ', self::parse($value));
    }

    public static function permitsMotorcar(mixed $value): bool
    {
        return array_intersect(self::parse($value), ['D', 'DA']) !== [];
    }
}
