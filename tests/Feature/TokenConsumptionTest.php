<?php

use Carbon\Carbon;
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

// AC 1: Valid token consumption sets used_at and returns model
it('marks token as consumed and returns the cancellation token model', function () {
    $service = new CancellationTokenService;
    $user = TestUser::create();
    $booking = TestBooking::create();

    $plainToken = $service->create($booking, $user);
    $result = $service->consume($plainToken);

    expect($result)->toBeInstanceOf(CancellationToken::class)
        ->and($result->used_at)->not->toBeNull();
});

it('sets used_at to the current timestamp', function () {
    $service = new CancellationTokenService;
    $user = TestUser::create();
    $booking = TestBooking::create();

    $plainToken = $service->create($booking, $user);
    $result = $service->consume($plainToken);

    expect($result->used_at)->toBeInstanceOf(Carbon::class)
        ->and($result->used_at->diffInSeconds(now()))->toBeLessThanOrEqual(2);
});

// AC 2: Already-consumed token throws Consumed
it('throws TokenVerificationException for an already-consumed token', function () {
    $service = new CancellationTokenService;
    $user = TestUser::create();
    $booking = TestBooking::create();

    $plainToken = $service->create($booking, $user);
    $service->consume($plainToken);

    // Second consumption should fail
    $service->consume($plainToken);
})->throws(TokenVerificationException::class);

it('sets the reason to Consumed for a previously consumed token', function () {
    $service = new CancellationTokenService;
    $user = TestUser::create();
    $booking = TestBooking::create();

    $plainToken = $service->create($booking, $user);
    $service->consume($plainToken);

    try {
        $service->consume($plainToken);
    } catch (TokenVerificationException $e) {
        expect($e->reason)->toBe(TokenVerificationFailure::Consumed);

        return;
    }

    throw new Exception('Expected TokenVerificationException was not thrown');
});

// AC 3: Expired token throws Expired
it('throws TokenVerificationException for an expired token', function () {
    $service = new CancellationTokenService;
    $user = TestUser::create();
    $booking = TestBooking::create();

    $plainToken = $service->create($booking, $user);

    $token = CancellationToken::where('cancellable_id', $booking->id)->firstOrFail();
    $token->expires_at = now()->subHour();
    $token->save();

    $service->consume($plainToken);
})->throws(TokenVerificationException::class);

it('sets the reason to Expired for an expired token on consume', function () {
    $service = new CancellationTokenService;
    $user = TestUser::create();
    $booking = TestBooking::create();

    $plainToken = $service->create($booking, $user);

    $token = CancellationToken::where('cancellable_id', $booking->id)->firstOrFail();
    $token->expires_at = now()->subHour();
    $token->save();

    try {
        $service->consume($plainToken);
    } catch (TokenVerificationException $e) {
        expect($e->reason)->toBe(TokenVerificationFailure::Expired);

        return;
    }

    throw new Exception('Expected TokenVerificationException was not thrown');
});

// Non-existent token throws NotFound (implicit from verify reuse)
it('throws TokenVerificationException with NotFound reason for a non-existent token on consume', function () {
    $service = new CancellationTokenService;

    $service->consume('ct_nonexistent_token_string_that_does_not_match_any_hash');
})->throws(TokenVerificationException::class);

it('sets the reason to NotFound for a non-existent token on consume', function () {
    $service = new CancellationTokenService;

    try {
        $service->consume('ct_nonexistent_token_string');
    } catch (TokenVerificationException $e) {
        expect($e->reason)->toBe(TokenVerificationFailure::NotFound);

        return;
    }

    throw new Exception('Expected TokenVerificationException was not thrown');
});

// AC 4: Consumed token fails subsequent verify()
it('throws Consumed on verify() after the token has been consumed', function () {
    $service = new CancellationTokenService;
    $user = TestUser::create();
    $booking = TestBooking::create();

    $plainToken = $service->create($booking, $user);
    $service->consume($plainToken);

    try {
        $service->verify($plainToken);
    } catch (TokenVerificationException $e) {
        expect($e->reason)->toBe(TokenVerificationFailure::Consumed);

        return;
    }

    throw new Exception('Expected TokenVerificationException was not thrown');
});

// Edge case: Token that is both consumed AND expired throws Consumed (not Expired)
it('throws Consumed (not Expired) for a token that is both consumed and expired on consume', function () {
    $service = new CancellationTokenService;
    $user = TestUser::create();
    $booking = TestBooking::create();

    $plainToken = $service->create($booking, $user);

    $token = CancellationToken::where('cancellable_id', $booking->id)->firstOrFail();
    $token->used_at = now()->subHour();
    $token->expires_at = now()->subMinute();
    $token->save();

    try {
        $service->consume($plainToken);
    } catch (TokenVerificationException $e) {
        expect($e->reason)->toBe(TokenVerificationFailure::Consumed);

        return;
    }

    throw new Exception('Expected TokenVerificationException was not thrown');
});
