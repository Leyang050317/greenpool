<?php

namespace App\Services\Ocr;

final class DocumentTypeDetector
{
    public const DRIVING_LICENCE = 'DRIVING_LICENCE';

    public const VEHICLE_GERAN = 'VEHICLE_GERAN';

    public function detect(string $text): ?string
    {
        $text = mb_strtoupper($text);
        $licence = $this->score($text, ['LESEN MEMANDU', 'DRIVING LICENCE', 'NO. PENGENALAN', 'KELAS', 'TEMPOH']);
        $geran = $this->score($text, ['SIJIL PEMILIKAN KENDERAAN', 'VEHICLE OWNERSHIP CERTIFICATE', 'NO. PENDAFTARAN', 'NAMA PEMUNYA BERDAFTAR', 'NO. CHASIS', 'NO. ENJIN']);

        if (max($licence, $geran) < 2 || $licence === $geran) {
            return null;
        }

        return $licence > $geran ? self::DRIVING_LICENCE : self::VEHICLE_GERAN;
    }

    private function score(string $text, array $keywords): int
    {
        return count(array_filter($keywords, fn (string $keyword) => str_contains($text, $keyword)));
    }
}
