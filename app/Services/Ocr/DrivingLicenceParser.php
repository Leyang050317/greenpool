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

        $identityLabel = $this->lineIndex($lines, 'IDENTITY NO');
        if ($identityLabel !== null) {
            $fields['name'] = $this->unlabelledName($lines, $identityLabel, $fields['name']);
            $fields['identity_no'] = $this->lineValue($lines[$identityLabel + 1] ?? null);
            $fields['date_of_birth'] = $this->lineValue($lines[$identityLabel + 2] ?? null);
        }

        $nationalityLabel = $this->lineIndex($lines, 'NATIONALITY');
        if ($nationalityLabel !== null) {
            $classLabel = $this->lineIndex($lines, 'CLASS', $nationalityLabel);
            $valueIndex = max($nationalityLabel, $classLabel ?? $nationalityLabel) + 1;
            $fields['nationality'] = $this->lineValue($lines[$valueIndex] ?? null);
            $fields['licence_class'] = $this->lineValue($lines[$valueIndex + 1] ?? null);
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
