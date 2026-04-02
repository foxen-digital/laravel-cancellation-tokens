<?php

namespace Foxen\CancellationToken\Events;

use Foxen\CancellationToken\Models\CancellationToken;

readonly class TokenConsumed
{
    public function __construct(
        public CancellationToken $token,
    ) {}
}
