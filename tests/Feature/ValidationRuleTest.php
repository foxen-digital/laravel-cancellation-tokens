<?php

use Foxen\CancellationToken\Contracts\CancellationTokenContract;
use Foxen\CancellationToken\Enums\TokenVerificationFailure;
use Foxen\CancellationToken\Rules\ValidCancellationToken;
use Foxen\CancellationToken\Tests\Fixtures\TestBooking;
use Foxen\CancellationToken\Tests\Fixtures\TestUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

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

// AC 1: Valid token passes validation
it('passes validation for a valid token', function () {
    $booking = TestBooking::create();
    $user = TestUser::create();
    $plainToken = app(CancellationTokenContract::class)->create($booking, $user);

    $rule = new ValidCancellationToken;
    $validator = Validator::make(
        ['token' => $plainToken],
        ['token' => [$rule]],
    );

    expect($validator->fails())->toBeFalse();
    expect($rule->failureReason)->toBeNull();
});

// AC 2: Non-existent token fails validation, reason is NotFound
it('fails validation for a non-existent token with NotFound reason', function () {
    $rule = new ValidCancellationToken;
    $validator = Validator::make(
        ['token' => 'ct_nonexistent_token_string'],
        ['token' => [$rule]],
    );

    expect($validator->fails())->toBeTrue();
    expect($rule->failureReason)->toBe(TokenVerificationFailure::NotFound);
});

// AC 3: Expired token fails validation, reason is Expired
it('fails validation for an expired token with Expired reason', function () {
    $booking = TestBooking::create();
    $user = TestUser::create();
    $plainToken = app(CancellationTokenContract::class)->create($booking, $user, now()->addSeconds(5));

    $this->travel(6)->seconds();

    $rule = new ValidCancellationToken;
    $validator = Validator::make(
        ['token' => $plainToken],
        ['token' => [$rule]],
    );

    expect($validator->fails())->toBeTrue();
    expect($rule->failureReason)->toBe(TokenVerificationFailure::Expired);
});

// AC 4: Consumed token fails validation, reason is Consumed
it('fails validation for a consumed token with Consumed reason', function () {
    $booking = TestBooking::create();
    $user = TestUser::create();
    $plainToken = app(CancellationTokenContract::class)->create($booking, $user);

    app(CancellationTokenContract::class)->consume($plainToken);

    $rule = new ValidCancellationToken;
    $validator = Validator::make(
        ['token' => $plainToken],
        ['token' => [$rule]],
    );

    expect($validator->fails())->toBeTrue();
    expect($rule->failureReason)->toBe(TokenVerificationFailure::Consumed);
});

// AC 5: Validation error message is generic — no failure-specific wording
it('returns a generic error message that does not expose the failure reason', function () {
    $booking = TestBooking::create();
    $user = TestUser::create();
    $plainToken = app(CancellationTokenContract::class)->create($booking, $user, now()->addSeconds(5));

    $this->travel(6)->seconds();

    $rule = new ValidCancellationToken;
    $validator = Validator::make(
        ['token' => $plainToken],
        ['token' => [$rule]],
    );

    expect($validator->fails())->toBeTrue();

    $message = $validator->errors()->first('token');
    expect($message)->not->toContain('expired');
    expect($message)->not->toContain('consumed');
    expect($message)->not->toContain('not found');
    expect($message)->not->toContain('NotFound');
    expect($message)->not->toContain('Expired');
    expect($message)->not->toContain('Consumed');
});

// AC 1 + property hygiene: failureReason is null after passing validation
it('has a null failure reason after a passing validation', function () {
    $booking = TestBooking::create();
    $user = TestUser::create();
    $plainToken = app(CancellationTokenContract::class)->create($booking, $user);

    $rule = new ValidCancellationToken;
    $validator = Validator::make(
        ['token' => $plainToken],
        ['token' => [$rule]],
    );

    expect($validator->fails())->toBeFalse();
    expect($rule->failureReason)->toBeNull();
});

// Form request simulation: rule works via Validator::make integration
it('works via Validator::make integration simulating form request', function () {
    $booking = TestBooking::create();
    $user = TestUser::create();
    $plainToken = app(CancellationTokenContract::class)->create($booking, $user);

    $rule = new ValidCancellationToken;

    // Passing case
    $passValidator = Validator::make(
        ['token' => $plainToken],
        ['token' => ['required', $rule]],
    );
    expect($passValidator->passes())->toBeTrue();

    // Failing case — non-existent token
    $failRule = new ValidCancellationToken;
    $failValidator = Validator::make(
        ['token' => 'ct_nonexistent'],
        ['token' => ['required', $failRule]],
    );
    expect($failValidator->passes())->toBeFalse();
    expect($failRule->failureReason)->toBe(TokenVerificationFailure::NotFound);
});
