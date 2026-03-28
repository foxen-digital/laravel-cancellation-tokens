<?php

namespace Foxen\CancellationToken\Facades;

use Foxen\CancellationToken\Contracts\CancellationTokenContract;
use Illuminate\Support\Facades\Facade;

/**
 * @see CancellationTokenContract
 */
class CancellationToken extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return CancellationTokenContract::class;
    }
}
