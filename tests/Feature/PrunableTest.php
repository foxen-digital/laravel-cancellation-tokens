<?php

use Foxen\CancellationToken\CancellationTokenService;
use Foxen\CancellationToken\Models\CancellationToken;
use Foxen\CancellationToken\Tests\Fixtures\TestBooking;
use Foxen\CancellationToken\Tests\Fixtures\TestUser;
use Illuminate\Database\Eloquent\Builder;
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

// AC 1: Expired tokens are pruned
it('prunes expired tokens', function () {
    $service = new CancellationTokenService;
    $user = TestUser::create();
    $user2 = TestUser::create();
    $booking = TestBooking::create();

    $service->create($booking, $user, now()->addHour());
    $token = CancellationToken::first();
    $token->expires_at = now()->subHour();
    $token->save();

    $service->create($booking, $user2, now()->addDay());

    expect(CancellationToken::count())->toBe(2);

    $pruned = (new CancellationToken)->pruneAll();

    expect($pruned)->toBe(1);
    expect(CancellationToken::count())->toBe(1);
    expect(CancellationToken::first()->expires_at->isFuture())->toBeTrue();
});

// AC 1: Consumed tokens are pruned
it('prunes consumed tokens', function () {
    $service = new CancellationTokenService;
    $user = TestUser::create();
    $booking = TestBooking::create();

    $service->create($booking, $user, now()->addDay());
    $consumed = CancellationToken::first();
    $consumed->used_at = now();
    $consumed->save();

    $user2 = TestUser::create();
    $service->create($booking, $user2, now()->addDay());
    $valid = CancellationToken::where('tokenable_id', $user2->id)
        ->where('tokenable_type', TestUser::class)
        ->first();

    expect(CancellationToken::count())->toBe(2);

    $pruned = (new CancellationToken)->pruneAll();

    expect($pruned)->toBe(1);
    expect(CancellationToken::count())->toBe(1);
    expect(CancellationToken::first()->id)->toBe($valid->id);
    expect(CancellationToken::first()->used_at)->toBeNull();
});

// AC 1: Consumed token with no expiry is pruned
it('prunes consumed tokens with no expiry', function () {
    $service = new CancellationTokenService;
    $user = TestUser::create();
    $booking = TestBooking::create();

    $service->create($booking, $user);
    $token = CancellationToken::first();
    $token->expires_at = null;
    $token->used_at = now();
    $token->save();

    $pruned = (new CancellationToken)->pruneAll();

    expect($pruned)->toBe(1);
    expect(CancellationToken::count())->toBe(0);
});

// AC 2: Valid (unexpired, unconsumed) tokens are NOT pruned
it('preserves valid unexpired unconsumed tokens', function () {
    $service = new CancellationTokenService;
    $user = TestUser::create();
    $booking = TestBooking::create();

    $service->create($booking, $user, now()->addDay());

    expect(CancellationToken::count())->toBe(1);

    $pruned = (new CancellationToken)->pruneAll();

    expect($pruned)->toBe(0);
    expect(CancellationToken::count())->toBe(1);
});

// AC 2: Token with future expires_at AND null used_at survives
it('preserves tokens with future expiry and no consumption', function () {
    $service = new CancellationTokenService;
    $user = TestUser::create();
    $booking = TestBooking::create();

    $service->create($booking, $user, now()->addYear());

    $pruned = (new CancellationToken)->pruneAll();

    expect($pruned)->toBe(0);
    expect(CancellationToken::count())->toBe(1);
});

// AC 2 edge case: Token with null expires_at AND null used_at survives
it('preserves tokens with no expiry and no consumption', function () {
    $service = new CancellationTokenService;
    $user = TestUser::create();
    $booking = TestBooking::create();

    $service->create($booking, $user);
    $token = CancellationToken::first();
    $token->expires_at = null;
    $token->save();

    $pruned = (new CancellationToken)->pruneAll();

    expect($pruned)->toBe(0);
    expect(CancellationToken::count())->toBe(1);
});

// AC 1, 2: Mixed scenario — expired + consumed + valid coexist
it('deletes only expired and consumed tokens while preserving valid ones', function () {
    $service = new CancellationTokenService;
    $booking = TestBooking::create();

    // Expired token (user 1)
    $user1 = TestUser::create();
    $service->create($booking, $user1, now()->addHour());
    $expired = CancellationToken::where('tokenable_id', $user1->id)->where('tokenable_type', TestUser::class)->first();
    $expired->expires_at = now()->subHour();
    $expired->save();

    // Consumed token (user 2)
    $user2 = TestUser::create();
    $service->create($booking, $user2, now()->addDay());
    $consumed = CancellationToken::where('tokenable_id', $user2->id)->where('tokenable_type', TestUser::class)->first();
    $consumed->used_at = now();
    $consumed->save();

    // Valid token (user 3)
    $user3 = TestUser::create();
    $service->create($booking, $user3, now()->addDay());
    $valid = CancellationToken::where('tokenable_id', $user3->id)->where('tokenable_type', TestUser::class)->first();

    // Another expired token (user 4)
    $user4 = TestUser::create();
    $service->create($booking, $user4, now()->addHour());
    $expired2 = CancellationToken::where('tokenable_id', $user4->id)->where('tokenable_type', TestUser::class)->first();
    $expired2->expires_at = now()->subMinute();
    $expired2->save();

    expect(CancellationToken::count())->toBe(4);

    $pruned = (new CancellationToken)->pruneAll();

    expect($pruned)->toBe(3);
    expect(CancellationToken::count())->toBe(1);
    expect(CancellationToken::first()->id)->toBe($valid->id);
});

// AC 1: pruneAll() returns count of pruned records
it('returns the count of pruned records', function () {
    $service = new CancellationTokenService;
    $booking = TestBooking::create();

    // Create 3 expired tokens using different users
    for ($i = 0; $i < 3; $i++) {
        $user = TestUser::create();
        $service->create($booking, $user, now()->addHour());
        $token = CancellationToken::where('tokenable_id', $user->id)->first();
        $token->expires_at = now()->subHour();
        $token->save();
    }

    $pruned = (new CancellationToken)->pruneAll();

    expect($pruned)->toBe(3);
    expect(CancellationToken::count())->toBe(0);
});

// AC 3: Chunked deletion
it('prunes tokens in chunks', function () {
    $service = new CancellationTokenService;
    $booking = TestBooking::create();

    // Create 5 expired tokens using different users
    for ($i = 0; $i < 5; $i++) {
        $user = TestUser::create();
        $service->create($booking, $user, now()->addHour());
        $token = CancellationToken::where('tokenable_id', $user->id)->first();
        $token->expires_at = now()->subHour();
        $token->save();
    }

    expect(CancellationToken::count())->toBe(5);

    // Use chunk size of 2 to verify chunked processing works
    $pruned = (new CancellationToken)->pruneAll(2);

    expect($pruned)->toBe(5);
    expect(CancellationToken::count())->toBe(0);
});

// AC 4: Model uses Prunable trait
it('uses the Prunable trait and defines prunable method', function () {
    expect(method_exists(CancellationToken::class, 'prunable'))->toBeTrue();
    expect(in_array('Illuminate\Database\Eloquent\Prunable', class_uses(CancellationToken::class)))->toBeTrue();
});

// AC 5: Custom model subclass can override prunable()
it('allows custom pruning criteria via model extension', function () {
    $service = new CancellationTokenService;
    $user = TestUser::create();
    $booking = TestBooking::create();

    // Create an expired token
    $service->create($booking, $user, now()->addHour());
    $token = CancellationToken::first();
    $token->expires_at = now()->subHour();
    $token->save();

    // Custom model: only prune consumed tokens (NOT expired)
    $customModel = new class extends CancellationToken
    {
        public function prunable(): Builder
        {
            return self::whereNotNull('used_at');
        }
    };

    // Expired token should NOT be pruned by custom criteria
    $pruned = $customModel->pruneAll();
    expect($pruned)->toBe(0);
    expect(CancellationToken::count())->toBe(1);
});
