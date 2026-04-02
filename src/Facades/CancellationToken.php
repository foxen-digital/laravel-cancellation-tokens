<?php

namespace Foxen\CancellationToken\Facades;

use Foxen\CancellationToken\Contracts\CancellationTokenContract;
use Foxen\CancellationToken\Testing\CancellationTokenFake;
use Illuminate\Database\Eloquent\Model;
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

    public static function fake(): CancellationTokenFake
    {
        $fake = new CancellationTokenFake;
        static::clearResolvedInstance(static::getFacadeAccessor());
        app()->instance(CancellationTokenContract::class, $fake);

        return $fake;
    }

    public static function assertTokenCreatedFor(Model $cancellable, ?Model $tokenable = null): void
    {
        self::guardFakeActive(__FUNCTION__)->assertTokenCreatedFor($cancellable, $tokenable);
    }

    public static function assertTokenConsumed(string $plainToken): void
    {
        self::guardFakeActive(__FUNCTION__)->assertTokenConsumed($plainToken);
    }

    public static function assertTokenNotConsumed(string $plainToken): void
    {
        self::guardFakeActive(__FUNCTION__)->assertTokenNotConsumed($plainToken);
    }

    public static function assertNoTokensCreated(): void
    {
        self::guardFakeActive(__FUNCTION__)->assertNoTokensCreated();
    }

    private static function guardFakeActive(string $method): CancellationTokenFake
    {
        $root = static::getFacadeRoot();

        if (! $root instanceof CancellationTokenFake) {
            throw new \BadMethodCallException(
                "CancellationToken::{$method}() may only be called after CancellationToken::fake()."
            );
        }

        return $root;
    }
}
