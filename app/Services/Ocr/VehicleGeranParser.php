<?php

namespace App\Services\Ocr;

class VehicleGeranParser extends DocumentParser
{
    public function parse(array $lines): array
    {
        $map = [
            'voc_reference_no' => ['NO. RUJUKAN VOC', 'VOC REFERENCE NO.'],
            'registration_no' => ['NO. PENDAFTARAN', 'NO.PENDAFTARAN', 'REGISTRATION NO.'],
            'owner_identity_no' => ['NO. PENGENALAN PEMUNYA', 'NO. ID', 'NO.ID', 'NO. 1D', 'NO.1D', 'OWNER IDENTITY NO.'],
            'registered_owner_name' => ['NAMA PEMUNYA BERDAFTAR', 'NAMA PEAUNYA BERDAFTAR', 'REGISTERED OWNER NAME'],
            'owner_address' => ['ALAMAT', 'ADDRESS'],
            'chassis_no' => ['NO. CHASIS', 'CHASSIS NO.'],
            'engine_no' => ['NO. ENJIN', 'ENGINE NO.'],
            'manufacturer' => ['BUATAN', 'MAKE'],
            'model_name' => ['NAMA MODEL', 'MODEL'],
            'engine_capacity' => ['KEUPAYAAN ENJIN', 'ENGINE CAPACITY'],
            'fuel_type' => ['BAHAN BAKAR', 'FUEL TYPE'],
            'origin_status' => ['STATUS ASAL', 'ORIGIN STATUS'],
            'usage_class' => ['KELAS KEGUNAAN', 'USAGE CLASS'],
            'body_type' => ['JENIS BADAN', 'BODY TYPE'],
            'manufacturing_year' => ['TAHUN DIBUAT', 'MANUFACTURING YEAR'],
            'registration_date' => ['TARIKH PENDAFTARAN', 'REGISTRATION DATE'],
            'bdm' => ['BDM'], 'bgk' => ['BGK'], 'btm' => ['BTM'],
            'registration_condition_1' => ['SYARAT PENDAFTARAN 1'],
            'registration_condition_2' => ['SYARAT PENDAFTARAN 2'],
            'registration_condition_3' => ['SYARAT PENDAFTARAN 3'],
        ];

        $fields = array_map(fn (array $labels) => $this->field($lines, $labels), $map);
        $fields['voc_reference_no'] = $this->vocReference($lines, $fields['voc_reference_no']);
        $fields['owner_address'] = $this->multilineAddress($lines, $fields['owner_address']);
        $this->splitCombinedField($fields, $lines, ['NO. CHASIS / NO. ENJIN', 'NO. CHASIS/NO. ENJIN'], 'chassis_no', 'engine_no');
        $this->splitCombinedField($fields, $lines, ['BUATAN / NAMA MODEL', 'BUATAN/NAMA MODEL'], 'manufacturer', 'model_name');
        $this->splitCombinedField($fields, $lines, ['JENIS BADAN / TAHUN DIBUAT', 'JENIS BADAN/TAHUN DIBUAT'], 'body_type', 'manufacturing_year');

        return $fields;
    }

    private function vocReference(array $lines, array $fallback): array
    {
        if ($fallback['value'] !== null) {
            return $fallback;
        }

        $maxX = collect($lines)->flatMap(fn (array $line) => $line['bounding_box'] ?? [])->max(fn ($point) => is_array($point) ? ($point[0] ?? 0) : 0) ?: 0;
        $maxY = collect($lines)->flatMap(fn (array $line) => $line['bounding_box'] ?? [])->max(fn ($point) => is_array($point) ? ($point[1] ?? 0) : 0) ?: 0;

        foreach ($lines as $line) {
            $value = trim((string) ($line['text'] ?? ''));
            $box = $line['bounding_box'] ?? null;
            if (preg_match('/^[A-Z0-9]{7,12}$/D', mb_strtoupper($value)) !== 1 || ! is_array($box) || $box === []) {
                continue;
            }
            $centerX = collect($box)->avg(fn ($point) => is_array($point) ? ($point[0] ?? 0) : 0);
            $centerY = collect($box)->avg(fn ($point) => is_array($point) ? ($point[1] ?? 0) : 0);
            if ($centerX >= $maxX * 0.75 && $centerY <= $maxY * 0.35) {
                return $this->value($value, $line['confidence'] ?? null);
            }
        }

        return $fallback;
    }

    private function multilineAddress(array $lines, array $fallback): array
    {
        foreach ($lines as $index => $line) {
            if (! str_contains(mb_strtoupper((string) ($line['text'] ?? '')), 'ALAMAT')) {
                continue;
            }

            $values = [];
            $confidences = [];
            for ($cursor = $index + 1; isset($lines[$cursor]); $cursor++) {
                $text = trim((string) ($lines[$cursor]['text'] ?? ''), " \t\n\r\0\x0B:");
                if (preg_match('/^(NO\.?\s*CHASIS|CHASSIS\s+NO)/iu', $text) === 1) {
                    break;
                }
                if ($text !== '') {
                    $values[] = $text;
                    if (is_numeric($lines[$cursor]['confidence'] ?? null)) {
                        $confidences[] = (float) $lines[$cursor]['confidence'];
                    }
                }
            }

            if ($values !== []) {
                return $this->value(implode("\n", $values), $confidences === [] ? null : min($confidences));
            }
        }

        return $fallback;
    }

    private function splitCombinedField(array &$fields, array $lines, array $labels, string $left, string $right): void
    {
        $combined = $this->field($lines, $labels);
        if (! is_string($combined['value']) || ! str_contains($combined['value'], '/')) {
            return;
        }

        [$leftValue, $rightValue] = array_map(fn (string $value) => trim($value, " \t\n\r\0\x0B:"), explode('/', $combined['value'], 2));
        $fields[$left] = $this->value($leftValue, $combined['confidence']);
        $fields[$right] = $this->value($rightValue, $combined['confidence']);
    }
}
