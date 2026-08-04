<?php

namespace App\Services\Routing;

use RuntimeException;

class TripRoutingException extends RuntimeException
{
    public function __construct(public readonly string $errorField, string $message)
    {
        parent::__construct($message);
    }
}
