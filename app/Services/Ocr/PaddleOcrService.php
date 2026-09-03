<?php

namespace App\Services\Ocr;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use JsonException;

class PaddleOcrService
{
    public function scan(UploadedFile $file): array
    {
        $result = Process::timeout(config('ocr.timeout'))
            ->env($this->windowsEnvironment())
            ->run([
                $this->pythonBinary(),
                config('ocr.worker_path'),
                $file->getRealPath(),
            ]);

        if ($result->failed()) {
            $diagnostic = $this->safeDiagnostic($result->errorOutput());
            Log::warning('Local OCR worker failed.', [
                'exit_code' => $result->exitCode(),
                'diagnostic' => $diagnostic,
            ]);

            $message = 'Document scanning is temporarily unavailable. Please enter the details manually or try again later.';
            if (app()->isLocal() && $diagnostic !== '') {
                $message .= ' Local OCR error: '.$diagnostic;
            }

            throw new OcrException($message);
        }

        try {
            $payload = json_decode($result->output(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new OcrException('The OCR engine returned an unreadable response.');
        }

        if (! is_array($payload) || empty($payload['lines'])) {
            throw new OcrException('No readable text was detected. Please upload a clearer document image.');
        }

        return $payload;
    }

    private function pythonBinary(): string
    {
        $configured = (string) config('ocr.python_binary');
        $deployedVirtualEnvironment = base_path('.venv-ocr/bin/python');

        if (PHP_OS_FAMILY !== 'Windows'
            && in_array($configured, ['python', 'python3'], true)
            && is_file($deployedVirtualEnvironment)) {
            return $deployedVirtualEnvironment;
        }

        return $configured;
    }

    private function safeDiagnostic(string $errorOutput): string
    {
        $decoded = json_decode(trim($errorOutput), true);
        if (is_array($decoded) && is_string($decoded['error'] ?? null)) {
            return mb_substr(preg_replace('/\s+/u', ' ', trim($decoded['error'])) ?? '', 0, 500);
        }

        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/u', $errorOutput) ?: [])));

        return mb_substr((string) end($lines), 0, 500);
    }

    private function windowsEnvironment(): array
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return [];
        }

        $systemRoot = getenv('SystemRoot') ?: getenv('WINDIR') ?: 'C:\\Windows';
        $userProfile = getenv('USERPROFILE')
            ?: rtrim((string) getenv('HOMEDRIVE'), '\\/').(getenv('HOMEPATH') ?: '')
            ?: storage_path('app');
        $localAppData = getenv('LOCALAPPDATA') ?: $userProfile.'\\AppData\\Local';
        $temp = getenv('TEMP') ?: getenv('TMP') ?: $localAppData.'\\Temp';

        return [
            'SystemRoot' => $systemRoot,
            'SYSTEMROOT' => $systemRoot,
            'WINDIR' => getenv('WINDIR') ?: $systemRoot,
            'windir' => getenv('windir') ?: $systemRoot,
            'COMSPEC' => getenv('COMSPEC') ?: $systemRoot.'\\System32\\cmd.exe',
            'PATH' => getenv('PATH') ?: '',
            'PATHEXT' => getenv('PATHEXT') ?: '.COM;.EXE;.BAT;.CMD',
            'USERPROFILE' => $userProfile,
            'HOME' => getenv('HOME') ?: $userProfile,
            'HOMEDRIVE' => getenv('HOMEDRIVE') ?: substr($userProfile, 0, 2),
            'HOMEPATH' => getenv('HOMEPATH') ?: substr($userProfile, 2),
            'APPDATA' => getenv('APPDATA') ?: $userProfile.'\\AppData\\Roaming',
            'LOCALAPPDATA' => $localAppData,
            'TEMP' => $temp,
            'TMP' => $temp,
        ];
    }
}
