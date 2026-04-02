<?php

namespace Foxen\CancellationToken\Testing;

use Carbon\Carbon;
use Foxen\CancellationToken\Contracts\CancellationTokenContract;
use Foxen\CancellationToken\Enums\TokenVerificationFailure;
use Foxen\CancellationToken\Exceptions\TokenVerificationException;
use Foxen\CancellationToken\Models\CancellationToken as CancellationTokenModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use PHPUnit\Framework\Assert;

class CancellationTokenFake implements CancellationTokenContract
{
    protected array $createdTokens = [];

    public function create(Model $cancellable, Model $tokenable, ?Carbon $expiresAt = null): string
    {
        $plainToken = 'ct_fake_'.Str::random(32);

        $this->createdTokens[$plainToken] = [
            'cancellable' => $cancellable,
            'tokenable' => $tokenable,
            'expiresAt' => $expiresAt,
            'consumed' => false,
        ];

        return $plainToken;
    }

    public function verify(string $plainToken): CancellationTokenModel
    {
        if (! array_key_exists($plainToken, $this->createdTokens)) {
            throw new TokenVerificationException(TokenVerificationFailure::NotFound);
        }

        $record = $this->createdTokens[$plainToken];

        if ($record['expiresAt'] !== null && $record['expiresAt']->isPast()) {
            throw new TokenVerificationException(TokenVerificationFailure::Expired);
        }

        if ($record['consumed']) {
            throw new TokenVerificationException(TokenVerificationFailure::Consumed);
        }

        return $this->makeModel($record);
    }

    public function consume(string $plainToken): CancellationTokenModel
    {
        if (! array_key_exists($plainToken, $this->createdTokens)) {
            throw new TokenVerificationException(TokenVerificationFailure::NotFound);
        }

        $record = &$this->createdTokens[$plainToken];

        if ($record['expiresAt'] !== null && $record['expiresAt']->isPast()) {
            throw new TokenVerificationException(TokenVerificationFailure::Expired);
        }

        if ($record['consumed']) {
            throw new TokenVerificationException(TokenVerificationFailure::Consumed);
        }

        $record['consumed'] = true;

        return $this->makeModel($record, consumed: true);
    }

    public function assertTokenCreatedFor(Model $cancellable, ?Model $tokenable = null): void
    {
        if ($cancellable->getKey() === null) {
            Assert::fail(sprintf(
                'assertTokenCreatedFor: %s has no primary key — set $model->id before asserting.',
                $cancellable::class
            ));
        }

        if ($tokenable !== null && $tokenable->getKey() === null) {
            Assert::fail(sprintf(
                'assertTokenCreatedFor: %s has no primary key — set $model->id before asserting.',
                $tokenable::class
            ));
        }

        $found = collect($this->createdTokens)->contains(function ($record) use ($cancellable, $tokenable) {
            $matchesCancellable = $cancellable::class === $record['cancellable']::class
                && $record['cancellable']->getKey() === $cancellable->getKey();

            if ($tokenable === null) {
                return $matchesCancellable;
            }

            return $matchesCancellable
                && $tokenable::class === $record['tokenable']::class
                && $record['tokenable']->getKey() === $tokenable->getKey();
        });

        if (! $found) {
            Assert::fail(
                $tokenable
                    ? 'Expected a token to be created for the given cancellable and tokenable.'
                    : 'Expected a token to be created for the given cancellable.'
            );
        }
    }

    public function assertTokenConsumed(string $plainToken): void
    {
        if (! array_key_exists($plainToken, $this->createdTokens)) {
            Assert::fail('assertTokenConsumed: token not found — was it created with this fake?');
        }

        if (! $this->createdTokens[$plainToken]['consumed']) {
            Assert::fail('Expected token to be consumed.');
        }
    }

    public function assertTokenNotConsumed(string $plainToken): void
    {
        if (! array_key_exists($plainToken, $this->createdTokens)) {
            Assert::fail('assertTokenNotConsumed: token not found — was it created with this fake?');
        }

        if ($this->createdTokens[$plainToken]['consumed']) {
            Assert::fail('Expected token not to be consumed.');
        }
    }

    public function assertNoTokensCreated(): void
    {
        if (! empty($this->createdTokens)) {
            Assert::fail('Expected no tokens to be created.');
        }
    }

    protected function makeModel(array $record, bool $consumed = false): CancellationTokenModel
    {
        $token = new CancellationTokenModel;
        $token->cancellable_type = $record['cancellable']::class;
        $token->cancellable_id = $record['cancellable']->getKey();
        $token->tokenable_type = $record['tokenable']::class;
        $token->tokenable_id = $record['tokenable']->getKey();
        $token->expires_at = $record['expiresAt'];
        $token->used_at = $consumed ? Carbon::now() : null;

        return $token;
    }
}
