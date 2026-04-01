<?php

namespace Foxen\CancellationToken\Exceptions;

use Exception;
use Foxen\CancellationToken\Enums\TokenVerificationFailure;

class TokenVerificationException extends Exception
{
    public function __construct(
        public readonly TokenVerificationFailure $reason,
        ?\Throwable $previous = null,
    ) {
        $message = 'Token verification failed';

        parent::__construct($message, 0, $previous);
    }
}
