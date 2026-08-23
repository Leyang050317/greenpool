<?php

namespace App\Services\Vehicle;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class VehicleImageValidationService
{
    public function validate(UploadedFile $image, string $expectedView): array
    {
        if (! config('vehicle_vision.enabled')) {
            throw new RuntimeException('Local vehicle photo validation is disabled.');
        }

        $process = Process::timeout(config('vehicle_vision.timeout'))
            ->env($this->windowsEnvironment())
            ->run([
                config('vehicle_vision.node_binary'),
                config('vehicle_vision.worker'),
                $image->getRealPath(),
                $expectedView,
                config('vehicle_vision.model'),
                config('vehicle_vision.cache_directory'),
            ]);
        if ($process->failed()) {
            report(new RuntimeException($process->errorOutput()));
            throw new RuntimeException('Local photo checking could not start. Please try again after the model finishes installing.');
        }

        $lines = preg_split('/\R/', trim($process->output()));
        $result = json_decode((string) end($lines), true);
        if (! is_array($result) || ! array_key_exists('view_matches', $result)) {
            throw new RuntimeException('The local photo checker returned an unreadable response.');
        }

        $accepted = ($result['is_vehicle'] ?? false)
            && ($result['view_matches'] ?? false)
            && ($result['quality'] ?? null) === 'ACCEPTABLE'
            && ! ($result['requires_review'] ?? true);
        $result['accepted'] = $accepted;
        $result['token'] = $accepted ? Crypt::encryptString(json_encode([
            'hash' => hash_file('sha256', $image->getRealPath()),
            'view' => $expectedView,
            'expires_at' => now()->addMinutes(30)->timestamp,
        ], JSON_THROW_ON_ERROR)) : null;

        return $result;
    }

    public function tokenMatches(string $token, UploadedFile $image, string $expectedView): bool
    {
        try {
            $payload = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);

            return ($payload['view'] ?? null) === $expectedView
                && ($payload['expires_at'] ?? 0) >= now()->timestamp
                && hash_equals((string) ($payload['hash'] ?? ''), hash_file('sha256', $image->getRealPath()));
        } catch (\Throwable) {
            return false;
        }
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
