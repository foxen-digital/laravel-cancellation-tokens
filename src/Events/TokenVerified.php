<?php

namespace Foxen\CancellationToken\Events;

use Foxen\CancellationToken\Models\CancellationToken;

readonly class TokenVerified
{
    public function __construct(
        public CancellationToken $token,
    ) {}
}
