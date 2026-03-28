<?php

use Foxen\CancellationToken\Models\CancellationToken;
use Foxen\CancellationToken\Tests\Fixtures\TestBooking;
use Foxen\CancellationToken\Tests\Fixtures\TestUser;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

// Helper to create tokens using forceFill (bypasses mass-assignment protection)
if (! function_exists('createTestToken')) {
    function createTestToken(array $data): CancellationToken
    {
        $token = new CancellationToken;
        $token->forceFill($data);
        $token->save();

        return $token;
    }
}

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

// AC 1: Configurable table name
it('uses the table name from config', function () {
    config(['cancellation-tokens.table' => 'cancellation_tokens']);

    $model = new CancellationToken;

    expect($model->getTable())->toBe('cancellation_tokens');
});

it('uses a custom table name when config is overridden', function () {
    config(['cancellation-tokens.table' => 'custom_token_table']);

    $model = new CancellationToken;

    expect($model->getTable())->toBe('custom_token_table');
});

it('falls back to default table name when config is not set', function () {
    config()->offsetUnset('cancellation-tokens.table');

    $model = new CancellationToken;

    expect($model->getTable())->toBe('cancellation_tokens');
});

// AC 2: Tokenable relationship
it('has a tokenable morphTo relationship', function () {
    $model = new CancellationToken;

    expect($model->tokenable())
        ->toBeInstanceOf(MorphTo::class);
});

it('returns the associated tokenable model', function () {
    $user = TestUser::create();
    $booking = TestBooking::create();

    $token = createTestToken([
        'token' => hash('sha256', 'test-token'),
        'tokenable_type' => TestUser::class,
        'tokenable_id' => $user->id,
        'cancellable_type' => TestBooking::class,
        'cancellable_id' => $booking->id,
    ]);

    expect($token->tokenable)
        ->toBeInstanceOf(TestUser::class)
        ->and($token->tokenable->id)->toBe($user->id);
});

// AC 3: Cancellable relationship
it('has a cancellable morphTo relationship', function () {
    $model = new CancellationToken;

    expect($model->cancellable())
        ->toBeInstanceOf(MorphTo::class);
});

it('returns the associated cancellable model', function () {
    $user = TestUser::create();
    $booking = TestBooking::create();

    $token = createTestToken([
        'token' => hash('sha256', 'test-token-2'),
        'tokenable_type' => TestUser::class,
        'tokenable_id' => $user->id,
        'cancellable_type' => TestBooking::class,
        'cancellable_id' => $booking->id,
    ]);

    expect($token->cancellable)
        ->toBeInstanceOf(TestBooking::class)
        ->and($token->cancellable->id)->toBe($booking->id);
});

// AC 4: Date casting
it('casts expires_at as datetime', function () {
    $token = createTestToken([
        'token' => hash('sha256', 'test-token-3'),
        'tokenable_type' => TestUser::class,
        'tokenable_id' => 1,
        'cancellable_type' => TestBooking::class,
        'cancellable_id' => 1,
        'expires_at' => now()->addDay(),
    ]);

    expect($token->expires_at)
        ->toBeInstanceOf(Carbon\Carbon::class);
});

it('casts used_at as datetime', function () {
    $token = createTestToken([
        'token' => hash('sha256', 'test-token-4'),
        'tokenable_type' => TestUser::class,
        'tokenable_id' => 1,
        'cancellable_type' => TestBooking::class,
        'cancellable_id' => 1,
        'used_at' => now(),
    ]);

    expect($token->used_at)
        ->toBeInstanceOf(Carbon\Carbon::class);
});

it('returns null for expires_at when not set', function () {
    $token = createTestToken([
        'token' => hash('sha256', 'test-token-5'),
        'tokenable_type' => TestUser::class,
        'tokenable_id' => 1,
        'cancellable_type' => TestBooking::class,
        'cancellable_id' => 1,
    ]);

    expect($token->expires_at)->toBeNull();
});

it('returns null for used_at when not set', function () {
    $token = createTestToken([
        'token' => hash('sha256', 'test-token-6'),
        'tokenable_type' => TestUser::class,
        'tokenable_id' => 1,
        'cancellable_type' => TestBooking::class,
        'cancellable_id' => 1,
    ]);

    expect($token->used_at)->toBeNull();
});

// AC 5: No business logic (architecture test - verified by inspection)
it('does not contain token hashing logic', function () {
    $reflection = new ReflectionClass(CancellationToken::class);
    $methods = collect($reflection->getMethods())->map->getName();

    // Model should not have hashing methods
    expect($methods)
        ->not->toContain('hashToken')
        ->not->toContain('generateToken');
});

it('does not contain verification logic', function () {
    $reflection = new ReflectionClass(CancellationToken::class);
    $methods = collect($reflection->getMethods())->map->getName();

    // Model should not have verification methods
    expect($methods)
        ->not->toContain('verify')
        ->not->toContain('validate');
});

// AC 6: Prunable integration
it('uses the Prunable trait', function () {
    $traits = class_uses(CancellationToken::class);

    expect($traits)->toHaveKey(Prunable::class);
});

it('returns prunable query for expired tokens', function () {
    // Create expired token
    $expiredToken = createTestToken([
        'token' => hash('sha256', 'expired-token'),
        'tokenable_type' => TestUser::class,
        'tokenable_id' => 1,
        'cancellable_type' => TestBooking::class,
        'cancellable_id' => 1,
        'expires_at' => now()->subDay(),
    ]);

    // Create valid token
    $validToken = createTestToken([
        'token' => hash('sha256', 'valid-token'),
        'tokenable_type' => TestUser::class,
        'tokenable_id' => 1,
        'cancellable_type' => TestBooking::class,
        'cancellable_id' => 1,
        'expires_at' => now()->addDay(),
    ]);

    $prunable = new CancellationToken;
    $query = $prunable->prunable();

    $prunableIds = $query->pluck('id');

    expect($prunableIds)
        ->toContain($expiredToken->id)
        ->not->toContain($validToken->id);
});

it('returns prunable query for consumed tokens', function () {
    // Create consumed token
    $consumedToken = createTestToken([
        'token' => hash('sha256', 'consumed-token'),
        'tokenable_type' => TestUser::class,
        'tokenable_id' => 1,
        'cancellable_type' => TestBooking::class,
        'cancellable_id' => 1,
        'expires_at' => now()->addDay(),
        'used_at' => now(),
    ]);

    // Create unconsumed token
    $unconsumedToken = createTestToken([
        'token' => hash('sha256', 'unconsumed-token'),
        'tokenable_type' => TestUser::class,
        'tokenable_id' => 1,
        'cancellable_type' => TestBooking::class,
        'cancellable_id' => 1,
        'expires_at' => now()->addDay(),
    ]);

    $prunable = new CancellationToken;
    $query = $prunable->prunable();

    $prunableIds = $query->pluck('id');

    expect($prunableIds)
        ->toContain($consumedToken->id)
        ->not->toContain($unconsumedToken->id);
});

it('excludes valid unconsumed tokens from prunable query', function () {
    // Create valid, unconsumed token
    $validToken = createTestToken([
        'token' => hash('sha256', 'valid-unconsumed'),
        'tokenable_type' => TestUser::class,
        'tokenable_id' => 1,
        'cancellable_type' => TestBooking::class,
        'cancellable_id' => 1,
        'expires_at' => now()->addDay(),
        'used_at' => null,
    ]);

    $prunable = new CancellationToken;
    $query = $prunable->prunable();

    $prunableIds = $query->pluck('id');

    expect($prunableIds)
        ->not->toContain($validToken->id);
});

it('excludes token with null expires_at and null used_at from prunable query', function () {
    // A never-expiring, never-consumed token should never be pruned
    $permanentToken = createTestToken([
        'token' => hash('sha256', 'permanent-token'),
        'tokenable_type' => TestUser::class,
        'tokenable_id' => 1,
        'cancellable_type' => TestBooking::class,
        'cancellable_id' => 1,
        'expires_at' => null,
        'used_at' => null,
    ]);

    $prunableIds = (new CancellationToken)->prunable()->pluck('id');

    expect($prunableIds)->not->toContain($permanentToken->id);
});

// AC 7: Mass-assignment protection
it('protects token from mass-assignment', function () {
    CancellationToken::create([
        'token' => hash('sha256', 'test-mass-assign-1'),
        'tokenable_type' => TestUser::class,
        'tokenable_id' => 1,
        'cancellable_type' => TestBooking::class,
        'cancellable_id' => 1,
    ]);
})->throws(MassAssignmentException::class);

it('protects tokenable_type from mass-assignment', function () {
    CancellationToken::create([
        'tokenable_type' => 'MaliciousClass',
        'tokenable_id' => 1,
        'cancellable_type' => TestBooking::class,
        'cancellable_id' => 1,
    ]);
})->throws(MassAssignmentException::class);

it('protects tokenable_id from mass-assignment', function () {
    CancellationToken::create([
        'tokenable_id' => 999,
        'cancellable_type' => TestBooking::class,
        'cancellable_id' => 1,
    ]);
})->throws(MassAssignmentException::class);

it('protects cancellable_type from mass-assignment', function () {
    CancellationToken::create([
        'cancellable_type' => 'MaliciousClass',
        'cancellable_id' => 1,
    ]);
})->throws(MassAssignmentException::class);

it('protects cancellable_id from mass-assignment', function () {
    CancellationToken::create([
        'cancellable_id' => 999,
    ]);
})->throws(MassAssignmentException::class);

it('protects expires_at from mass-assignment', function () {
    CancellationToken::create([
        'expires_at' => now()->addYear(),
    ]);
})->throws(MassAssignmentException::class);

it('protects used_at from mass-assignment', function () {
    CancellationToken::create([
        'used_at' => now(),
    ]);
})->throws(MassAssignmentException::class);

it('allows forceFill to bypass mass-assignment protection', function () {
    $token = new CancellationToken;
    $token->forceFill([
        'token' => hash('sha256', 'forcefill-token'),
        'tokenable_type' => TestUser::class,
        'tokenable_id' => 1,
        'cancellable_type' => TestBooking::class,
        'cancellable_id' => 1,
        'expires_at' => now()->addDay(),
    ]);
    $token->save();

    expect($token->token)->toBe(hash('sha256', 'forcefill-token'))
        ->and($token->tokenable_type)->toBe(TestUser::class)
        ->and($token->tokenable_id)->toBe(1);
});
