<?php

namespace Foxen\CancellationToken;

use Carbon\Carbon;
use Foxen\CancellationToken\Contracts\CancellationTokenContract;
use Foxen\CancellationToken\Enums\TokenVerificationFailure;
use Foxen\CancellationToken\Events\TokenConsumed;
use Foxen\CancellationToken\Events\TokenCreated;
use Foxen\CancellationToken\Events\TokenExpired;
use Foxen\CancellationToken\Events\TokenVerified;
use Foxen\CancellationToken\Exceptions\TokenVerificationException;
use Foxen\CancellationToken\Models\CancellationToken;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CancellationTokenService implements CancellationTokenContract
{
    /**
     * Create a new cancellation token.
     *
     * @param  Model  $cancellable  The model that can be cancelled
     * @param  Model  $tokenable  The actor requesting cancellation
     * @param  Carbon|null  $expiresAt  Custom expiry timestamp (defaults to config)
     * @return string The plain-text token (only returned once)
     */
    public function create(Model $cancellable, Model $tokenable, ?Carbon $expiresAt = null): string
    {
        if (! $cancellable->exists) {
            throw new \InvalidArgumentException('$cancellable must be a persisted model.');
        }

        if (! $tokenable->exists) {
            throw new \InvalidArgumentException('$tokenable must be a persisted model.');
        }

        if ($expiresAt !== null && $expiresAt->isPast()) {
            throw new \InvalidArgumentException('$expiresAt must be a future timestamp.');
        }

        $expiresAt ??= now()->addMinutes((int) config('cancellation-tokens.default_expiry', 10080));

        CancellationToken::where('cancellable_type', $cancellable::class)
            ->where('cancellable_id', $cancellable->getKey())
            ->where('tokenable_type', $tokenable::class)
            ->where('tokenable_id', $tokenable->getKey())
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->delete();

        $plainToken = $this->generatePlainTextToken();
        $hashedToken = $this->hashToken($plainToken);

        $token = new CancellationToken;
        $token->token = $hashedToken;
        $token->tokenable_type = $tokenable::class;
        $token->tokenable_id = $tokenable->getKey();
        $token->cancellable_type = $cancellable::class;
        $token->cancellable_id = $cancellable->getKey();
        $token->expires_at = $expiresAt;
        $token->save();

        event(new TokenCreated($token));

        return $plainToken;
    }

    public function verify(string $plainToken): CancellationToken
    {
        $computedHash = $this->hashToken($plainToken);

        $stored = CancellationToken::where('token', $computedHash)->first();

        if ($stored === null) {
            throw new TokenVerificationException(TokenVerificationFailure::NotFound);
        }

        if ($stored->used_at !== null) {
            throw new TokenVerificationException(TokenVerificationFailure::Consumed);
        }

        if ($stored->expires_at !== null && $stored->expires_at->isPast()) {
            event(new TokenExpired($stored));
            throw new TokenVerificationException(TokenVerificationFailure::Expired);
        }

        event(new TokenVerified($stored));

        return $stored;
    }

    public function consume(string $plainToken): CancellationToken
    {
        $token = $this->verify($plainToken);
        $token->used_at = now();
        $token->save();

        event(new TokenConsumed($token));

        return $token;
    }

    /**
     * Generate a plain-text token with the configured prefix.
     */
    private function generatePlainTextToken(): string
    {
        $prefix = config('cancellation-tokens.prefix', 'ct_');

        return $prefix.Str::random(64);
    }

    /**
     * Hash a plain-text token using HMAC-SHA256.
     */
    private function hashToken(string $plainToken): string
    {
        return hash_hmac('sha256', $plainToken, config('app.key'));
    }
}
