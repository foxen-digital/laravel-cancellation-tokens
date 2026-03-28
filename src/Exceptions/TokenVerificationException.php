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
        $message = match ($reason) {
            TokenVerificationFailure::NotFound => 'Token not found',
            TokenVerificationFailure::Expired => 'Token has expired',
            TokenVerificationFailure::Consumed => 'Token has already been consumed',
        };

        parent::__construct($message, 0, $previous);
    }
}
