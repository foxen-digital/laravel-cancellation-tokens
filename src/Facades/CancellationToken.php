<?php

namespace Foxen\CancellationToken\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Foxen\CancellationToken\CancellationToken
 */
class CancellationToken extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Foxen\CancellationToken\CancellationToken::class;
    }
}
