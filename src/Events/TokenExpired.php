<?php

namespace Foxen\CancellationToken\Events;

use Foxen\CancellationToken\Models\CancellationToken;

readonly class TokenExpired
{
    public function __construct(
        public CancellationToken $token,
    ) {}
}
