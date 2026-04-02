<?php

use Carbon\Carbon;
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

it('creates a valid token record with hashed token and future expiry', function () {
    $token = CancellationToken::factory()->create();

    expect($token->token)->toMatch('/^[0-9a-f]{64}$/')
        ->and($token->expires_at)->toBeInstanceOf(Carbon::class)
        ->and($token->expires_at->isFuture())->toBeTrue()
        ->and($token->used_at)->toBeNull();
});

it('creates a consumed token via state', function () {
    $token = CancellationToken::factory()->consumed()->create();

    expect($token->used_at)->not->toBeNull();
});

it('creates an expired token via state', function () {
    $token = CancellationToken::factory()->expired()->create();

    expect($token->expires_at->isPast())->toBeTrue();
});

it('associates with a cancellable model via for()', function () {
    $booking = TestBooking::create();

    $token = CancellationToken::factory()
        ->for($booking, 'cancellable')
        ->create();

    expect($token->cancellable_type)->toBe(TestBooking::class)
        ->and($token->cancellable_id)->toBe($booking->id);
});

it('associates with a tokenable model via for()', function () {
    $user = TestUser::create();

    $token = CancellationToken::factory()
        ->for($user, 'tokenable')
        ->create();

    expect($token->tokenable_type)->toBe(TestUser::class)
        ->and($token->tokenable_id)->toBe($user->id);
});

it('stores token as a 64-character lowercase hex string', function () {
    $token = CancellationToken::factory()->create();

    expect(strlen($token->token))->toBe(64)
        ->and(ctype_xdigit($token->token))->toBeTrue();
});
