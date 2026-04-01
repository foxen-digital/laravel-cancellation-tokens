<?php

use Foxen\CancellationToken\CancellationTokenService;
use Foxen\CancellationToken\Contracts\CancellationTokenContract;
use Foxen\CancellationToken\Enums\TokenVerificationFailure;
use Foxen\CancellationToken\Exceptions\TokenVerificationException;
use Foxen\CancellationToken\Facades\CancellationToken;
use Foxen\CancellationToken\Models\CancellationToken as CancellationTokenModel;
use Foxen\CancellationToken\Tests\Fixtures\TestBooking;
use Foxen\CancellationToken\Tests\Fixtures\TestUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    if (! Schema::hasTable(config('cancellation-tokens.table', 'cancellation_tokens'))) {
        $migration = include __DIR__.'/../../database/migrations/create_cancellation_tokens_table.php';
        $migration->up();
    }
    if (! Schema::hasTable('test_users')) {
        Schema::create('test_users', fn ($table) => $table->id());
    }
    if (! Schema::hasTable('test_bookings')) {
        Schema::create('test_bookings', fn ($table) => $table->id());
    }
});

// AC 4: Container resolves CancellationTokenContract to CancellationTokenService
it('resolves the contract to the service implementation', function () {
    $resolved = app(CancellationTokenContract::class);

    expect($resolved)->toBeInstanceOf(CancellationTokenService::class);
});

// AC 1: Facade create delegates and returns a plain-text token string
it('creates a cancellation token via the facade and returns a plain-text string', function () {
    $booking = TestBooking::create();
    $user = TestUser::create();

    $plainToken = CancellationToken::create(cancellable: $booking, tokenable: $user);

    expect($plainToken)->toBeString()
        ->toStartWith(config('cancellation-tokens.prefix', 'ct_'));
});

// AC 1: Facade create with explicit expiresAt persists correctly
it('creates a token with an explicit expiry timestamp via the facade', function () {
    $booking = TestBooking::create();
    $user = TestUser::create();
    $expiresAt = now()->addDays(3);

    $plainToken = CancellationToken::create(cancellable: $booking, tokenable: $user, expiresAt: $expiresAt);

    expect($plainToken)->toBeString()->toStartWith(config('cancellation-tokens.prefix', 'ct_'));
    $token = CancellationTokenModel::where('cancellable_type', TestBooking::class)
        ->where('cancellable_id', $booking->id)
        ->first();
    expect($token)->not->toBeNull();
    expect($token->expires_at->toDateTimeString())->toBe($expiresAt->toDateTimeString());
});

// AC 2: Facade verify on a valid token returns the CancellationToken model
it('verifies a valid token and returns the cancellation token model', function () {
    $booking = TestBooking::create();
    $user = TestUser::create();

    $plainToken = CancellationToken::create(cancellable: $booking, tokenable: $user);
    $result = CancellationToken::verify($plainToken);

    expect($result)->toBeInstanceOf(CancellationTokenModel::class);
    expect($result->cancellable_type)->toBe(TestBooking::class);
    expect($result->cancellable_id)->toBe($booking->id);
});

// AC 2: Facade verify on an expired token throws TokenVerificationException
it('throws token verification exception on expired token verification', function () {
    $booking = TestBooking::create();
    $user = TestUser::create();

    $plainToken = CancellationToken::create(cancellable: $booking, tokenable: $user, expiresAt: now()->addSeconds(5));

    $this->travel(6)->seconds();
    CancellationToken::verify($plainToken);
})->throws(TokenVerificationException::class);

// AC 2: Expired verify sets reason to Expired
it('sets the reason to expired for an expired token via facade verify', function () {
    $booking = TestBooking::create();
    $user = TestUser::create();

    $plainToken = CancellationToken::create(cancellable: $booking, tokenable: $user, expiresAt: now()->addSeconds(5));

    $this->travel(6)->seconds();
    try {
        CancellationToken::verify($plainToken);
    } catch (TokenVerificationException $e) {
        expect($e->reason)->toBe(TokenVerificationFailure::Expired);

        return;
    }

    throw new Exception('Expected TokenVerificationException was not thrown');
});

// AC 2: Facade verify on a consumed token throws TokenVerificationException
it('throws token verification exception on consumed token verification', function () {
    $booking = TestBooking::create();
    $user = TestUser::create();

    $plainToken = CancellationToken::create(cancellable: $booking, tokenable: $user);
    CancellationToken::consume($plainToken);

    CancellationToken::verify($plainToken);
})->throws(TokenVerificationException::class);

// AC 2: Consumed verify sets reason to Consumed
it('sets the reason to consumed for a consumed token via facade verify', function () {
    $booking = TestBooking::create();
    $user = TestUser::create();

    $plainToken = CancellationToken::create(cancellable: $booking, tokenable: $user);
    CancellationToken::consume($plainToken);

    try {
        CancellationToken::verify($plainToken);
    } catch (TokenVerificationException $e) {
        expect($e->reason)->toBe(TokenVerificationFailure::Consumed);

        return;
    }

    throw new Exception('Expected TokenVerificationException was not thrown');
});

// AC 2: Facade verify on a non-existent token throws TokenVerificationException
it('throws token verification exception on non-existent token verification', function () {
    CancellationToken::verify('ct_nonexistent_token_string');
})->throws(TokenVerificationException::class);

// AC 2: NotFound verify sets reason to NotFound
it('sets the reason to not found for a non-existent token via facade verify', function () {
    try {
        CancellationToken::verify('ct_nonexistent_token_string');
    } catch (TokenVerificationException $e) {
        expect($e->reason)->toBe(TokenVerificationFailure::NotFound);

        return;
    }

    throw new Exception('Expected TokenVerificationException was not thrown');
});

// AC 3: Facade consume marks the token as used and returns the model
it('consumes a token and marks it as used', function () {
    $booking = TestBooking::create();
    $user = TestUser::create();

    $plainToken = CancellationToken::create(cancellable: $booking, tokenable: $user);
    $result = CancellationToken::consume($plainToken);

    expect($result)->toBeInstanceOf(CancellationTokenModel::class);
    expect($result->used_at)->not->toBeNull();
});

// AC 3: Facade consume on an already-consumed token throws TokenVerificationException
it('throws token verification exception on already consumed token', function () {
    $booking = TestBooking::create();
    $user = TestUser::create();

    $plainToken = CancellationToken::create(cancellable: $booking, tokenable: $user);
    CancellationToken::consume($plainToken);

    CancellationToken::consume($plainToken);
})->throws(TokenVerificationException::class);

// AC 3: Already-consumed consume sets reason to Consumed
it('sets the reason to consumed for an already consumed token via facade consume', function () {
    $booking = TestBooking::create();
    $user = TestUser::create();

    $plainToken = CancellationToken::create(cancellable: $booking, tokenable: $user);
    CancellationToken::consume($plainToken);

    try {
        CancellationToken::consume($plainToken);
    } catch (TokenVerificationException $e) {
        expect($e->reason)->toBe(TokenVerificationFailure::Consumed);

        return;
    }

    throw new Exception('Expected TokenVerificationException was not thrown');
});

// AC 3: Facade consume on an expired token throws TokenVerificationException
it('throws token verification exception on expired token consumption', function () {
    $booking = TestBooking::create();
    $user = TestUser::create();

    $plainToken = CancellationToken::create(cancellable: $booking, tokenable: $user, expiresAt: now()->addSeconds(5));

    $this->travel(6)->seconds();
    CancellationToken::consume($plainToken);
})->throws(TokenVerificationException::class);

// AC 3: Expired consume sets reason to Expired
it('sets the reason to expired for an expired token via facade consume', function () {
    $booking = TestBooking::create();
    $user = TestUser::create();

    $plainToken = CancellationToken::create(cancellable: $booking, tokenable: $user, expiresAt: now()->addSeconds(5));

    $this->travel(6)->seconds();
    try {
        CancellationToken::consume($plainToken);
    } catch (TokenVerificationException $e) {
        expect($e->reason)->toBe(TokenVerificationFailure::Expired);

        return;
    }

    throw new Exception('Expected TokenVerificationException was not thrown');
});

// Architectural verification: facade accessor returns CancellationTokenContract::class
it('returns the contract class as the facade accessor', function () {
    $method = (new ReflectionClass(CancellationToken::class))
        ->getMethod('getFacadeAccessor');
    $method->setAccessible(true);

    expect($method->invoke(null))->toBe(CancellationTokenContract::class);
});
