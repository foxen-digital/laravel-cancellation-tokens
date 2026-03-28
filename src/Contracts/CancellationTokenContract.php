<?php

namespace Foxen\CancellationToken\Contracts;

use Carbon\Carbon;
use Foxen\CancellationToken\Exceptions\TokenVerificationException;
use Foxen\CancellationToken\Models\CancellationToken;
use Illuminate\Database\Eloquent\Model;

interface CancellationTokenContract
{
    /**
     * Create a new cancellation token.
     *
     * @param  Model  $cancellable  The model that can be cancelled
     * @param  Model  $tokenable  The actor requesting cancellation
     * @param  Carbon|null  $expiresAt  Custom expiry timestamp (defaults to config)
     * @return string The plain-text token (only returned once)
     */
    public function create(Model $cancellable, Model $tokenable, ?Carbon $expiresAt = null): string;

    /**
     * Verify a token without consuming it.
     *
     * @param  string  $plainToken  The plain-text token to verify
     * @return CancellationToken The token model
     *
     * @throws TokenVerificationException
     */
    public function verify(string $plainToken): CancellationToken;

    /**
     * Verify and consume a token (single-use).
     *
     * @param  string  $plainToken  The plain-text token to consume
     * @return CancellationToken The token model (now marked as used)
     *
     * @throws TokenVerificationException
     */
    public function consume(string $plainToken): CancellationToken;
}
