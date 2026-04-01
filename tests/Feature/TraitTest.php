<?php

use Foxen\CancellationToken\Models\CancellationToken;
use Foxen\CancellationToken\Tests\Fixtures\TestBooking;
use Foxen\CancellationToken\Tests\Fixtures\TestUser;
use Illuminate\Database\Eloquent\Relations\MorphMany;
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

// AC 1: Trait-based token creation — returns plain-text token string
it('creates a cancellation token via the trait and returns a plain-text string', function () {
    $booking = TestBooking::create();
    $user = TestUser::create();

    $plainToken = $booking->createCancellationToken(tokenable: $user);

    expect($plainToken)->toBeString()
        ->toStartWith(config('cancellation-tokens.prefix', 'ct_'));
});

// AC 1: Explicit expiresAt is persisted correctly
it('creates a token with an explicit expiry timestamp', function () {
    $booking = TestBooking::create();
    $user = TestUser::create();
    $expiresAt = now()->addDays(3);

    $plainToken = $booking->createCancellationToken(tokenable: $user, expiresAt: $expiresAt);

    expect($plainToken)->toBeString()->toStartWith(config('cancellation-tokens.prefix', 'ct_'));
    $token = CancellationToken::where('cancellable_type', TestBooking::class)->where('cancellable_id', $booking->id)->first();
    expect($token->expires_at->toDateTimeString())->toBe($expiresAt->toDateTimeString());
});

// AC 1: Default expiry from config when no expiresAt provided
it('creates a token with the default expiry when none is provided', function () {
    $booking = TestBooking::create();
    $user = TestUser::create();
    $defaultExpiry = now()->addMinutes((int) config('cancellation-tokens.default_expiry', 10080));

    $plainToken = $booking->createCancellationToken(tokenable: $user);

    expect($plainToken)->toBeString()->toStartWith(config('cancellation-tokens.prefix', 'ct_'));
    $token = CancellationToken::where('cancellable_type', TestBooking::class)->where('cancellable_id', $booking->id)->first();
    expect(abs($token->expires_at->diffInSeconds($defaultExpiry)))->toBeLessThan(2);
});

// AC 2: cancellationTokens() returns morphMany relationship
it('returns all cancellation tokens for the cancellable model', function () {
    $booking = TestBooking::create();
    $user = TestUser::create();
    $user2 = TestUser::create();

    $booking->createCancellationToken(tokenable: $user);
    $booking->createCancellationToken(tokenable: $user2);

    $tokens = $booking->cancellationTokens;

    expect($tokens)->toHaveCount(2);
    expect($tokens->first())->toBeInstanceOf(CancellationToken::class);
});

// AC 2: cancellationTokens() only returns tokens for this model instance
it('does not return tokens belonging to other cancellable models', function () {
    $booking1 = TestBooking::create();
    $booking2 = TestBooking::create();
    $user = TestUser::create();

    $booking1->createCancellationToken(tokenable: $user);
    $booking2->createCancellationToken(tokenable: $user);

    expect($booking1->cancellationTokens)->toHaveCount(1);
    expect($booking2->cancellationTokens)->toHaveCount(1);
});

// AC 3: Trait is strictly opt-in — models without it lack the methods
it('does not add trait methods to models that do not use the trait', function () {
    $user = new TestUser;

    expect(method_exists($user, 'createCancellationToken'))->toBeFalse()
        ->and(method_exists($user, 'cancellationTokens'))->toBeFalse();
});

// AC 4: Non-User tokenable support
it('creates a token with a non-User model as the tokenable actor', function () {
    $booking = TestBooking::create();
    $anotherBooking = TestBooking::create();

    $plainToken = $booking->createCancellationToken(tokenable: $anotherBooking);

    expect($plainToken)->toBeString()->toStartWith(config('cancellation-tokens.prefix', 'ct_'));
    $token = CancellationToken::where('cancellable_type', TestBooking::class)->where('cancellable_id', $booking->id)->first();
    expect($token->tokenable_type)->toBe(TestBooking::class);
    expect($token->tokenable_id)->toBe($anotherBooking->id);
});

// AC 1: Persisted cancellable_type/id are correct
it('persists the correct cancellable type and id on the token record', function () {
    $booking = TestBooking::create();
    $user = TestUser::create();

    $booking->createCancellationToken(tokenable: $user);

    $token = CancellationToken::where('cancellable_type', TestBooking::class)->where('cancellable_id', $booking->id)->first();
    expect($token->cancellable_type)->toBe(TestBooking::class);
    expect($token->cancellable_id)->toBe($booking->id);
});

// AC 2: cancellationTokens() method returns a MorphMany relationship instance
it('cancellationTokens() method returns a MorphMany relationship instance', function () {
    $booking = TestBooking::create();

    $relationship = $booking->cancellationTokens();

    expect($relationship)->toBeInstanceOf(MorphMany::class);
});
