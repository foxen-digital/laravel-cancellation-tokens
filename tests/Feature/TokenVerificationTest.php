<?php

use Foxen\CancellationToken\CancellationTokenService;
use Foxen\CancellationToken\Enums\TokenVerificationFailure;
use Foxen\CancellationToken\Exceptions\TokenVerificationException;
use Foxen\CancellationToken\Models\CancellationToken;
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
        Schema::create('test_users', function ($table) {
            $table->id();
        });
    }

    if (! Schema::hasTable('test_bookings')) {
        Schema::create('test_bookings', function ($table) {
            $table->id();
        });
    }
});

// AC 1: Valid token returns CancellationToken model with accessible relationships
it('returns the cancellation token model for a valid token', function () {
    $service = new CancellationTokenService;
    $user = TestUser::create();
    $booking = TestBooking::create();

    $plainToken = $service->create($booking, $user);
    $result = $service->verify($plainToken);

    expect($result)->toBeInstanceOf(CancellationToken::class)
        ->and($result->tokenable)->toBeInstanceOf(TestUser::class)
        ->and($result->tokenable->id)->toBe($user->id)
        ->and($result->cancellable)->toBeInstanceOf(TestBooking::class)
        ->and($result->cancellable->id)->toBe($booking->id);
});

// AC 2: Non-existent token throws TokenVerificationException with NotFound reason
it('throws TokenVerificationException with NotFound reason for a non-existent token', function () {
    $service = new CancellationTokenService;

    $service->verify('ct_nonexistent_token_string_that_does_not_match_any_hash');
})->throws(TokenVerificationException::class);

it('sets the reason to NotFound for a non-existent token', function () {
    $service = new CancellationTokenService;

    try {
        $service->verify('ct_nonexistent_token_string');
    } catch (TokenVerificationException $e) {
        expect($e->reason)->toBe(TokenVerificationFailure::NotFound);

        return;
    }

    throw new Exception('Expected TokenVerificationException was not thrown');
});

// AC 3: Expired token throws TokenVerificationException with Expired reason
it('throws TokenVerificationException with Expired reason for an expired token', function () {
    $service = new CancellationTokenService;
    $user = TestUser::create();
    $booking = TestBooking::create();

    $plainToken = $service->create($booking, $user);

    // Expire the token
    $token = CancellationToken::first();
    $token->expires_at = now()->subHour();
    $token->save();

    $service->verify($plainToken);
})->throws(TokenVerificationException::class);

it('sets the reason to Expired for an expired token', function () {
    $service = new CancellationTokenService;
    $user = TestUser::create();
    $booking = TestBooking::create();

    $plainToken = $service->create($booking, $user);

    $token = CancellationToken::first();
    $token->expires_at = now()->subHour();
    $token->save();

    try {
        $service->verify($plainToken);
    } catch (TokenVerificationException $e) {
        expect($e->reason)->toBe(TokenVerificationFailure::Expired);

        return;
    }

    throw new Exception('Expected TokenVerificationException was not thrown');
});

// AC 4: Consumed token throws TokenVerificationException with Consumed reason
it('throws TokenVerificationException with Consumed reason for a consumed token', function () {
    $service = new CancellationTokenService;
    $user = TestUser::create();
    $booking = TestBooking::create();

    $plainToken = $service->create($booking, $user);

    // Consume the token
    $token = CancellationToken::first();
    $token->used_at = now();
    $token->save();

    $service->verify($plainToken);
})->throws(TokenVerificationException::class);

it('sets the reason to Consumed for a consumed token', function () {
    $service = new CancellationTokenService;
    $user = TestUser::create();
    $booking = TestBooking::create();

    $plainToken = $service->create($booking, $user);

    $token = CancellationToken::first();
    $token->used_at = now();
    $token->save();

    try {
        $service->verify($plainToken);
    } catch (TokenVerificationException $e) {
        expect($e->reason)->toBe(TokenVerificationFailure::Consumed);

        return;
    }

    throw new Exception('Expected TokenVerificationException was not thrown');
});

// AC 3+4 edge case: Token that is both consumed AND expired throws Consumed (not Expired)
it('throws Consumed (not Expired) for a token that is both consumed and expired', function () {
    $service = new CancellationTokenService;
    $user = TestUser::create();
    $booking = TestBooking::create();

    $plainToken = $service->create($booking, $user);

    $token = CancellationToken::first();
    $token->used_at = now()->subHour();
    $token->expires_at = now()->subMinute();
    $token->save();

    try {
        $service->verify($plainToken);
    } catch (TokenVerificationException $e) {
        expect($e->reason)->toBe(TokenVerificationFailure::Consumed);

        return;
    }

    throw new Exception('Expected TokenVerificationException was not thrown');
});

// AC 6: All failure cases throw the same exception class
it('throws the same exception class for all failure types', function () {
    $service = new CancellationTokenService;
    $user = TestUser::create();
    $booking = TestBooking::create();

    // Test NotFound
    expect(fn () => $service->verify('ct_nonexistent'))
        ->toThrow(TokenVerificationException::class);

    // Test Expired
    $plainToken = $service->create($booking, $user);
    $token = CancellationToken::where('cancellable_id', $booking->id)->first();
    $token->expires_at = now()->subHour();
    $token->save();

    expect(fn () => $service->verify($plainToken))
        ->toThrow(TokenVerificationException::class);

    // Test Consumed — create a fresh token
    $user2 = TestUser::create();
    $booking2 = TestBooking::create();
    $plainToken2 = $service->create($booking2, $user2);
    $token2 = CancellationToken::where('cancellable_id', $booking2->id)->first();
    $token2->used_at = now();
    $token2->save();

    expect(fn () => $service->verify($plainToken2))
        ->toThrow(TokenVerificationException::class);
});
