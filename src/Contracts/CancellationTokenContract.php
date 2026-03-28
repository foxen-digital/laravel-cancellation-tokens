<?php

namespace Foxen\CancellationToken\Contracts;

use Foxen\CancellationToken\Exceptions\TokenVerificationException;

interface CancellationTokenContract
{
    /**
     * Create a new cancellation token for the given cancellable model.
     *
     * @param  object  $cancellable  The model that can be cancelled
     * @param  object|null  $tokenable  The actor requesting cancellation (defaults to cancellable)
     * @param  int|null  $expiryMinutes  Custom expiry in minutes (defaults to config)
     * @return string The plain-text token (only returned once)
     */
    public function create(object $cancellable, ?object $tokenable = null, ?int $expiryMinutes = null): string;

    /**
     * Verify a token without consuming it.
     *
     * @param  string  $token  The plain-text token to verify
     * @param  object|null  $cancellable  Optional model to verify token ownership
     * @return object The CancellationToken model
     *
     * @throws TokenVerificationException
     */
    public function verify(string $token, ?object $cancellable = null): object;

    /**
     * Verify and consume a token (single-use).
     *
     * @param  string  $token  The plain-text token to consume
     * @param  object|null  $cancellable  Optional model to verify token ownership
     * @return object The CancellationToken model (now marked as used)
     *
     * @throws TokenVerificationException
     */
    public function consume(string $token, ?object $cancellable = null): object;
}
