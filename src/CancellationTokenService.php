<?php

namespace Foxen\CancellationToken;

use Foxen\CancellationToken\Contracts\CancellationTokenContract;

class CancellationTokenService implements CancellationTokenContract
{
    public function create(object $cancellable, ?object $tokenable = null, ?int $expiryMinutes = null): string
    {
        // Implementation will be completed in Story 2.3
        throw new \RuntimeException('Not implemented');
    }

    public function verify(string $token, ?object $cancellable = null): object
    {
        // Implementation will be completed in Story 2.4
        throw new \RuntimeException('Not implemented');
    }

    public function consume(string $token, ?object $cancellable = null): object
    {
        // Implementation will be completed in Story 2.5
        throw new \RuntimeException('Not implemented');
    }
}
