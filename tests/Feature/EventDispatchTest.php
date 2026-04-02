<?php

use Foxen\CancellationToken\CancellationTokenService;
use Foxen\CancellationToken\Enums\TokenVerificationFailure;
use Foxen\CancellationToken\Events\TokenConsumed;
use Foxen\CancellationToken\Events\TokenCreated;
use Foxen\CancellationToken\Events\TokenExpired;
use Foxen\CancellationToken\Events\TokenVerified;
use Foxen\CancellationToken\Exceptions\TokenVerificationException;
use Foxen\CancellationToken\Models\CancellationToken;
use Foxen\CancellationToken\Tests\Fixtures\TestBooking;
use Foxen\CancellationToken\Tests\Fixtures\TestUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
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

it('dispatches TokenCreated event on successful token creation', function () {
    Event::fake([TokenCreated::class]);

    $service = new CancellationTokenService;
    $user = TestUser::create();
    $booking = TestBooking::create();

    $plainToken = $service->create($booking, $user);

    Event::assertDispatched(TokenCreated::class, function ($event) use ($booking, $user) {
        return $event->token->cancellable instanceof TestBooking
            && $event->token->cancellable->id === $booking->id
            && $event->token->tokenable instanceof TestUser
            && $event->token->tokenable->id === $user->id;
    });
});

it('dispatches TokenVerified event on successful verification', function () {
    $service = new CancellationTokenService;
    $user = TestUser::create();
    $booking = TestBooking::create();

    $plainToken = $service->create($booking, $user);

    Event::fake([TokenVerified::class]);

    $service->verify($plainToken);

    Event::assertDispatched(TokenVerified::class, function ($event) use ($booking, $user) {
        return $event->token->cancellable instanceof TestBooking
            && $event->token->cancellable->id === $booking->id
            && $event->token->tokenable instanceof TestUser
            && $event->token->tokenable->id === $user->id;
    });
});

it('dispatches TokenConsumed event on successful consumption', function () {
    $service = new CancellationTokenService;
    $user = TestUser::create();
    $booking = TestBooking::create();

    $plainToken = $service->create($booking, $user);

    Event::fake([TokenConsumed::class]);

    $service->consume($plainToken);

    Event::assertDispatched(TokenConsumed::class, function ($event) use ($booking, $user) {
        return $event->token->cancellable instanceof TestBooking
            && $event->token->cancellable->id === $booking->id
            && $event->token->tokenable instanceof TestUser
            && $event->token->tokenable->id === $user->id
            && $event->token->used_at !== null;
    });
});

it('dispatches TokenExpired before throwing for an expired token on verify', function () {
    Event::fake([TokenExpired::class]);

    $service = new CancellationTokenService;
    $user = TestUser::create();
    $booking = TestBooking::create();

    $plainToken = $service->create($booking, $user);

    $token = CancellationToken::first();
    $token->expires_at = now()->subHour();
    $token->save();

    $caughtException = null;
    try {
        $service->verify($plainToken);
    } catch (TokenVerificationException $e) {
        $caughtException = $e;
    }

    expect($caughtException)->not->toBeNull()
        ->and($caughtException->reason)->toBe(TokenVerificationFailure::Expired);

    Event::assertDispatched(TokenExpired::class, function ($event) {
        return $event->token->expires_at->isPast();
    });
});

it('dispatches TokenExpired before throwing for an expired token on consume', function () {
    Event::fake([TokenExpired::class]);

    $service = new CancellationTokenService;
    $user = TestUser::create();
    $booking = TestBooking::create();

    $plainToken = $service->create($booking, $user);

    $token = CancellationToken::first();
    $token->expires_at = now()->subHour();
    $token->save();

    $caughtException = null;
    try {
        $service->consume($plainToken);
    } catch (TokenVerificationException $e) {
        $caughtException = $e;
    }

    expect($caughtException)->not->toBeNull()
        ->and($caughtException->reason)->toBe(TokenVerificationFailure::Expired);

    Event::assertDispatched(TokenExpired::class, function ($event) {
        return $event->token->expires_at->isPast();
    });
});

it('does not dispatch TokenExpired for NotFound or Consumed failure cases', function () {
    Event::fake([TokenExpired::class]);

    $service = new CancellationTokenService;
    $user = TestUser::create();
    $booking = TestBooking::create();

    // NotFound case
    try {
        $service->verify('nonexistent_token');
    } catch (TokenVerificationException $e) {
        expect($e->reason)->toBe(TokenVerificationFailure::NotFound);
    }

    Event::assertNotDispatched(TokenExpired::class);

    // Consumed case
    $plainToken = $service->create($booking, $user);
    $service->consume($plainToken);

    Event::fake([TokenExpired::class]);

    try {
        $service->verify($plainToken);
    } catch (TokenVerificationException $e) {
        expect($e->reason)->toBe(TokenVerificationFailure::Consumed);
    }

    Event::assertNotDispatched(TokenExpired::class);
});

it('ensures expired event token has past expires_at and exception reason is Expired', function () {
    Event::fake([TokenExpired::class]);

    $service = new CancellationTokenService;
    $user = TestUser::create();
    $booking = TestBooking::create();

    $plainToken = $service->create($booking, $user);

    $token = CancellationToken::first();
    $token->expires_at = now()->subHour();
    $token->save();

    $caughtException = null;
    try {
        $service->verify($plainToken);
    } catch (TokenVerificationException $e) {
        $caughtException = $e;
    }

    expect($caughtException)->not->toBeNull()
        ->and($caughtException->reason)->toBe(TokenVerificationFailure::Expired);

    Event::assertDispatched(TokenExpired::class, function ($event) {
        return $event->token->expires_at->isPast();
    });
});

it('does not dispatch TokenVerified on failure paths', function () {
    $service = new CancellationTokenService;
    $user = TestUser::create();
    $booking = TestBooking::create();

    // NotFound path
    Event::fake([TokenVerified::class]);
    try {
        $service->verify('nonexistent_token');
    } catch (TokenVerificationException $e) {
        expect($e->reason)->toBe(TokenVerificationFailure::NotFound);
    }
    Event::assertNotDispatched(TokenVerified::class);

    // Consumed path
    $plainToken = $service->create($booking, $user);
    $service->consume($plainToken);

    Event::fake([TokenVerified::class]);
    try {
        $service->verify($plainToken);
    } catch (TokenVerificationException $e) {
        expect($e->reason)->toBe(TokenVerificationFailure::Consumed);
    }
    Event::assertNotDispatched(TokenVerified::class);

    // Expired path
    $plainToken = $service->create($booking, $user);
    $token = CancellationToken::where('cancellable_id', $booking->id)
        ->whereNull('used_at')
        ->first();
    $token->expires_at = now()->subHour();
    $token->save();

    Event::fake([TokenVerified::class]);
    try {
        $service->verify($plainToken);
    } catch (TokenVerificationException $e) {
        expect($e->reason)->toBe(TokenVerificationFailure::Expired);
    }
    Event::assertNotDispatched(TokenVerified::class);
});

it('does not dispatch TokenConsumed on failure paths', function () {
    $service = new CancellationTokenService;
    $user = TestUser::create();
    $booking = TestBooking::create();

    // NotFound path
    Event::fake([TokenConsumed::class]);
    try {
        $service->consume('nonexistent_token');
    } catch (TokenVerificationException $e) {
        expect($e->reason)->toBe(TokenVerificationFailure::NotFound);
    }
    Event::assertNotDispatched(TokenConsumed::class);

    // Consumed path
    $plainToken = $service->create($booking, $user);
    $service->consume($plainToken);

    Event::fake([TokenConsumed::class]);
    try {
        $service->consume($plainToken);
    } catch (TokenVerificationException $e) {
        expect($e->reason)->toBe(TokenVerificationFailure::Consumed);
    }
    Event::assertNotDispatched(TokenConsumed::class);

    // Expired path
    $plainToken = $service->create($booking, $user);
    $token = CancellationToken::where('cancellable_id', $booking->id)
        ->whereNull('used_at')
        ->first();
    $token->expires_at = now()->subHour();
    $token->save();

    Event::fake([TokenConsumed::class]);
    try {
        $service->consume($plainToken);
    } catch (TokenVerificationException $e) {
        expect($e->reason)->toBe(TokenVerificationFailure::Expired);
    }
    Event::assertNotDispatched(TokenConsumed::class);
});
