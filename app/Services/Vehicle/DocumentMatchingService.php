<?php

namespace App\Services\Vehicle;

use App\Support\DocumentIdentity;

final class DocumentMatchingService
{
    public function match(array $licence, array $geran): array
    {
        $errors = [];
        $licenceName = DocumentIdentity::normalizeName($licence['name'] ?? null);
        $ownerName = DocumentIdentity::normalizeName($geran['registered_owner_name'] ?? null);
        if ($licenceName === '' || $licenceName !== $ownerName) {
            $errors[] = 'Registered owner name does not match the driving licence holder name.';
        }
        $licenceIdentity = $licence['identity_no'] ?? null;
        $ownerIdentity = $geran['owner_identity_no'] ?? null;
        if (! is_string($licenceIdentity) || preg_match('/^\d{12}$/D', $licenceIdentity) !== 1 || $licenceIdentity !== $ownerIdentity) {
            $errors[] = 'Registered owner identity number does not match the driving licence identity number.';
        }

        return [
            'status' => $errors === [] ? 'Verified' : 'Rejected',
            'message' => $errors === [] ? 'Driving licence holder matches the registered vehicle owner.' : implode(' ', $errors),
            'errors' => $errors,
        ];
    }
}
