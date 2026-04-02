<?php

use Foxen\CancellationToken\CancellationTokenService;
use Foxen\CancellationToken\Models\CancellationToken;
use Foxen\CancellationToken\Tests\Fixtures\TestBooking;
use Foxen\CancellationToken\Tests\Fixtures\TestUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Run the package migration only if the table does not yet exist
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

// AC 1: Token record created with correct polymorphic relationships and explicit expiry
it('creates a token record with correct relationships and explicit expiry', function () {
    $service = new CancellationTokenService;
    $user = TestUser::create();
    $booking = TestBooking::create();
    $expiresAt = now()->addDays(3);

    $plainToken = $service->create($booking, $user, $expiresAt);

    $token = CancellationToken::where('tokenable_type', TestUser::class)
        ->where('tokenable_id', $user->id)
        ->where('cancellable_type', TestBooking::class)
        ->where('cancellable_id', $booking->id)
        ->first();

    expect($token)->not->toBeNull()
        ->and($token->tokenable_type)->toBe(TestUser::class)
        ->and($token->tokenable_id)->toBe($user->id)
        ->and($token->cancellable_type)->toBe(TestBooking::class)
        ->and($token->cancellable_id)->toBe($booking->id)
        ->and($token->expires_at->format('Y-m-d H:i'))->toBe($expiresAt->format('Y-m-d H:i'));
});

// AC 2: Default expiry is applied from config
it('applies default expiry from config when not provided', function () {
    $service = new CancellationTokenService;
    $user = TestUser::create();
    $booking = TestBooking::create();

    $service->create($booking, $user);

    $token = CancellationToken::where('cancellable_id', $booking->id)->first();

    $expectedExpiry = now()->addMinutes(config('cancellation-tokens.default_expiry'));

    expect($token->expires_at->format('Y-m-d H:i'))->toBe($expectedExpiry->format('Y-m-d H:i'));
});

// AC 3: Stored token is HMAC-SHA256 hash, not plain-text
it('stores the token as an HMAC-SHA256 hash', function () {
    $service = new CancellationTokenService;
    $user = TestUser::create();
    $booking = TestBooking::create();

    $plainToken = $service->create($booking, $user);

    $storedHash = CancellationToken::where('cancellable_id', $booking->id)->value('token');

    // The stored hash should NOT be the plain token
    expect($storedHash)->not->toBe($plainToken);

    // The stored hash should be a valid SHA256 hex string (64 lowercase hex characters)
    expect($storedHash)->toMatch('/^[0-9a-f]{64}$/');
});

// AC 4: Returned token has correct format (prefix + 64 chars)
it('returns a token with the configured prefix and 64 random characters', function () {
    $service = new CancellationTokenService;
    $user = TestUser::create();
    $booking = TestBooking::create();

    $plainToken = $service->create($booking, $user);

    $prefix = config('cancellation-tokens.prefix', 'ct_');
    $suffix = substr($plainToken, strlen($prefix));

    expect($plainToken)->toStartWith($prefix)
        ->and(strlen($plainToken))->toBe(strlen($prefix) + 64)
        ->and($suffix)->toMatch('/^[a-zA-Z0-9]{64}$/');
});

it('respects a custom prefix from config', function () {
    config(['cancellation-tokens.prefix' => 'custom_']);

    $service = new CancellationTokenService;
    $user = TestUser::create();
    $booking = TestBooking::create();

    $plainToken = $service->create($booking, $user);

    expect($plainToken)->toStartWith('custom_')
        ->and(strlen($plainToken))->toBe(7 + 64); // 'custom_' is 7 chars
});

// AC 5: Multiple tokens are unique
it('generates unique tokens on successive calls', function () {
    $service = new CancellationTokenService;
    $user = TestUser::create();
    $booking = TestBooking::create();

    $token1 = $service->create($booking, $user);
    $token2 = $service->create($booking, $user);

    expect($token1)->not->toBe($token2);
});

// AC 6: Hash can be verified with hash_hmac()
it('stores a hash that can be verified with hash_hmac', function () {
    $service = new CancellationTokenService;
    $user = TestUser::create();
    $booking = TestBooking::create();

    $plainToken = $service->create($booking, $user);

    $storedHash = CancellationToken::where('cancellable_id', $booking->id)->value('token');
    $computedHash = hash_hmac('sha256', $plainToken, config('app.key'));

    expect(hash_equals($storedHash, $computedHash))->toBeTrue();
});

// Revocation: prior active tokens for the same pair are revoked on create
it('revokes existing active tokens for the same pair before inserting a new one', function () {
    $service = new CancellationTokenService;
    $user = TestUser::create();
    $booking = TestBooking::create();

    $service->create($booking, $user);
    $service->create($booking, $user);

    $activeCount = CancellationToken::where('cancellable_type', TestBooking::class)
        ->where('cancellable_id', $booking->id)
        ->where('tokenable_type', TestUser::class)
        ->where('tokenable_id', $user->id)
        ->whereNull('used_at')
        ->where('expires_at', '>', now())
        ->count();

    expect($activeCount)->toBe(1);
});

// Past expiry: throws InvalidArgumentException when $expiresAt is in the past
it('throws an exception when an explicit $expiresAt is in the past', function () {
    $service = new CancellationTokenService;
    $user = TestUser::create();
    $booking = TestBooking::create();

    expect(fn () => $service->create($booking, $user, now()->subMinute()))
        ->toThrow(InvalidArgumentException::class);
});

// Unsaved model guard: throws when cancellable is not persisted
it('throws an exception when the cancellable model is not persisted', function () {
    $service = new CancellationTokenService;
    $user = TestUser::create();
    $booking = new TestBooking;

    expect(fn () => $service->create($booking, $user))
        ->toThrow(InvalidArgumentException::class, '$cancellable must be a persisted model.');
});

// Unsaved model guard: throws when tokenable is not persisted
it('throws an exception when the tokenable model is not persisted', function () {
    $service = new CancellationTokenService;
    $user = new TestUser;
    $booking = TestBooking::create();

    expect(fn () => $service->create($booking, $user))
        ->toThrow(InvalidArgumentException::class, '$tokenable must be a persisted model.');
});

// Additional test: verify plain-text token is never in database
it('never stores the plain-text token in the database', function () {
    $service = new CancellationTokenService;
    $user = TestUser::create();
    $booking = TestBooking::create();

    $plainToken = $service->create($booking, $user);

    // Search for the plain token in the database
    $found = CancellationToken::where('token', $plainToken)->exists();

    expect($found)->toBeFalse();
});
