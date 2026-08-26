<?php

namespace App\Services\Ocr;

class DrivingLicenceParser extends DocumentParser
{
    public function parse(array $lines): array
    {
        $fields = [
            'name' => $this->field($lines, ['NAMA / NAME', 'NAMA', 'NAME']),
            'identity_no' => $this->field($lines, ['NO. PENGENALAN / IDENTITY NO.', 'NO. PENGENALAN', 'IDENTITY NO.']),
            'date_of_birth' => $this->field($lines, ['TARIKH LAHIR', 'DATE OF BIRTH']),
            'nationality' => $this->field($lines, ['WARGANEGARA', 'NATIONALITY']),
            'licence_class' => $this->field($lines, ['KELAS', 'CLASS']),
            'valid_from' => $this->field($lines, ['SAH DARI', 'VALID FROM']),
            'valid_until' => $this->field($lines, ['SAH HINGGA', 'VALID UNTIL', 'TEMPOH']),
            'address' => $this->field($lines, ['ALAMAT', 'ADDRESS']),
        ];

        $identityValueIndex = $this->identityValueIndex($lines);
        $identityLabel = $this->lineIndex($lines, 'IDENTITY NO');
        if ($identityLabel !== null) {
            $fields['name'] = $this->unlabelledName($lines, $identityLabel, $fields['name']);
            $fields['identity_no'] = $this->lineValue($lines[$identityLabel + 1] ?? null);
            $fields['date_of_birth'] = $this->lineValue($lines[$identityLabel + 2] ?? null);
        } elseif ($identityValueIndex !== null) {
            $fields['name'] = $this->unlabelledName($lines, $identityValueIndex, $fields['name']);
            $fields['identity_no'] = $this->lineValue($lines[$identityValueIndex]);
        }

        $nationalityLabel = $this->lineIndex($lines, 'NATIONALITY');
        if ($nationalityLabel !== null) {
            $classLabel = $this->lineIndex($lines, 'CLASS', $nationalityLabel);
            $nationalityValue = $this->firstValueBetween($lines, $nationalityLabel + 1, $classLabel ?? count($lines));
            if ($nationalityValue !== null) {
                $fields['nationality'] = $this->lineValue($lines[$nationalityValue]);
                if ($classLabel !== null) {
                    $classValue = $this->firstValueBetween($lines, $classLabel + 1, $this->lineIndex($lines, 'VALIDITY', $classLabel) ?? count($lines));
                    if ($classValue !== null) {
                        $fields['licence_class'] = $this->lineValue($lines[$classValue]);
                    }
                }
            } elseif ($classLabel !== null) {
                $fields['nationality'] = $this->lineValue($lines[$classLabel + 1] ?? null);
                $fields['licence_class'] = $this->lineValue($lines[$classLabel + 2] ?? null);
            }
        }

        $validityLabel = $this->lineIndex($lines, 'VALIDITY');
        if ($validityLabel !== null && isset($lines[$validityLabel + 1])) {
            $value = (string) ($lines[$validityLabel + 1]['text'] ?? '');
            if (preg_match('/(\d{2}[\/.-]\d{2}[\/.-]\d{4})\s*[-–]\s*(\d{2}[\/.-]\d{2}[\/.-]\d{4})/', $value, $matches) === 1) {
                $confidence = $lines[$validityLabel + 1]['confidence'] ?? null;
                $fields['valid_from'] = $this->value($matches[1], $confidence);
                $fields['valid_until'] = $this->value($matches[2], $confidence);
            }
        }

        $addressLabel = $this->lineIndex($lines, 'ADDRESS');
        if ($addressLabel !== null) {
            $addressLines = array_slice($lines, $addressLabel + 1);
            $values = array_values(array_filter(array_map(fn (array $line) => trim((string) ($line['text'] ?? ''), " \t\n\r\0\x0B:"), $addressLines)));
            $confidences = array_values(array_filter(array_map(fn (array $line) => is_numeric($line['confidence'] ?? null) ? (float) $line['confidence'] : null, $addressLines), fn ($value) => $value !== null));
            if ($values !== []) {
                $fields['address'] = $this->value(implode("\n", $values), $confidences === [] ? null : min($confidences));
            }
        }

        if ($fields['date_of_birth']['value'] === null) {
            $fields['date_of_birth'] = $this->dateOfBirthFromIdentity($fields['identity_no']);
        }

        return $fields;
    }

    private function lineIndex(array $lines, string $needle, int $after = 0): ?int
    {
        foreach ($lines as $index => $line) {
            if ($index >= $after && str_contains(mb_strtoupper((string) ($line['text'] ?? '')), $needle)) {
                return $index;
            }
        }

        return null;
    }

    private function lineValue(?array $line): array
    {
        return $this->value($line['text'] ?? null, $line['confidence'] ?? null);
    }

    private function identityValueIndex(array $lines): ?int
    {
        foreach ($lines as $index => $line) {
            if (preg_match('/^\d{12}$/D', trim((string) ($line['text'] ?? ''))) === 1) {
                return $index;
            }
        }

        return null;
    }

    private function firstValueBetween(array $lines, int $start, int $end): ?int
    {
        for ($index = $start; $index < $end; $index++) {
            $text = trim((string) ($lines[$index]['text'] ?? ''));
            if ($text === '' || preg_match('/PENGENALA(?:N)?|IDENTITY|NATIONALITY|WARGANEGARA|KELAS|CLASS/iu', $text) === 1
                || preg_match('/^\d{12}$/D', $text) === 1) {
                continue;
            }

            return $index;
        }

        return null;
    }

    private function dateOfBirthFromIdentity(array $identity): array
    {
        $identityNumber = (string) ($identity['value'] ?? '');
        if (preg_match('/^(\d{2})(\d{2})(\d{2})\d{6}$/D', $identityNumber, $matches) !== 1) {
            return $this->value(null, null);
        }

        $shortYear = (int) $matches[1];
        $month = (int) $matches[2];
        $day = (int) $matches[3];
        $currentYear = (int) date('Y');
        $latestDriverBirthYear = $currentYear - 17;
        $candidateYears = [2000 + $shortYear, 1900 + $shortYear];
        $year = collect($candidateYears)->first(fn (int $candidate) => $candidate <= $latestDriverBirthYear && $candidate >= $currentYear - 100);

        if (! is_int($year) || ! checkdate($month, $day, $year)) {
            return $this->value(null, null);
        }

        return $this->value(sprintf('%02d/%02d/%04d', $day, $month, $year), $identity['confidence'] ?? null);
    }

    private function unlabelledName(array $lines, int $identityLabel, array $fallback): array
    {
        for ($index = $identityLabel - 1; $index >= 0; $index--) {
            $text = trim((string) ($lines[$index]['text'] ?? ''));
            if (preg_match('/^[\pL]+(?:\s+[\pL]+)+$/u', $text) === 1
                && ! preg_match('/LESEN|MEMANDU|DRIVING|LICEN[CS]E|MALAYSIA/iu', $text)) {
                return $this->lineValue($lines[$index]);
            }
        }

        return $fallback;
    }
}
