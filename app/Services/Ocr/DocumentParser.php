<?php

namespace App\Services\Ocr;

abstract class DocumentParser
{
    public function __construct(protected readonly float $lowConfidenceThreshold = 0.75) {}

    abstract public function parse(array $lines): array;

    protected function field(array $lines, array $labels, int $offset = 1): array
    {
        foreach ($lines as $index => $line) {
            $text = trim((string) ($line['text'] ?? ''));
            foreach ($labels as $label) {
                if (preg_match('/'.preg_quote($label, '/').'\s*[:\-]?\s*(.+)$/iu', $text, $match)) {
                    return $this->value($match[1], $line['confidence'] ?? null);
                }
                if (str_contains(mb_strtoupper($text), mb_strtoupper($label)) && isset($lines[$index + $offset])) {
                    if (! $this->isNearbyValue($line, $lines[$index + $offset])) {
                        continue;
                    }

                    return $this->value($lines[$index + $offset]['text'] ?? null, $lines[$index + $offset]['confidence'] ?? null);
                }
            }
        }

        return $this->value(null, null);
    }

    protected function value(mixed $value, mixed $confidence): array
    {
        $value = is_string($value) ? trim($value, " \t\n\r\0\x0B:") : null;
        if ($value === '' || $value === '-') {
            $value = null;
        }
        $confidence = is_numeric($confidence) ? round((float) $confidence, 4) : null;

        return [
            'value' => $value,
            'confidence' => $confidence,
            'requires_review' => $value === null || ($confidence !== null && $confidence < $this->lowConfidenceThreshold),
        ];
    }

    private function isNearbyValue(array $label, array $candidate): bool
    {
        $labelBox = $label['bounding_box'] ?? null;
        $candidateBox = $candidate['bounding_box'] ?? null;
        if (! is_array($labelBox) || count($labelBox) < 2 || ! is_array($candidateBox) || count($candidateBox) < 2) {
            return true;
        }

        $labelY = collect($labelBox)->avg(fn ($point) => is_array($point) ? ($point[1] ?? 0) : 0);
        $candidateY = collect($candidateBox)->avg(fn ($point) => is_array($point) ? ($point[1] ?? 0) : 0);

        return abs($labelY - $candidateY) <= 18;
    }
}
