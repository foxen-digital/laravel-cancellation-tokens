<?php

namespace Foxen\CancellationToken;

use Carbon\Carbon;
use Foxen\CancellationToken\Contracts\CancellationTokenContract;
use Foxen\CancellationToken\Models\CancellationToken;
use Illuminate\Database\Eloquent\Model;

class CancellationTokenService implements CancellationTokenContract
{
    public function create(Model $cancellable, Model $tokenable, ?Carbon $expiresAt = null): string
    {
        // Implementation will be completed in Story 2.3
        throw new \RuntimeException('Not implemented');
    }

    public function verify(string $plainToken): CancellationToken
    {
        // Implementation will be completed in Story 2.4
        throw new \RuntimeException('Not implemented');
    }

    public function consume(string $plainToken): CancellationToken
    {
        // Implementation will be completed in Story 2.5
        throw new \RuntimeException('Not implemented');
    }
}
