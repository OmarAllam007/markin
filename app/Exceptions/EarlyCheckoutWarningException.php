<?php

namespace App\Exceptions;

use RuntimeException;

class EarlyCheckoutWarningException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $minutesEarly,
    ) {
        parent::__construct($message);
    }
}
