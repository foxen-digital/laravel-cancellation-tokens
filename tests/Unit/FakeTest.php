<?php

use Foxen\CancellationToken\Contracts\CancellationTokenContract;
use Foxen\CancellationToken\Facades\CancellationToken;
use Foxen\CancellationToken\Testing\CancellationTokenFake;
use Foxen\CancellationToken\Tests\Fixtures\TestBooking;
use Foxen\CancellationToken\Tests\Fixtures\TestUser;
use PHPUnit\Framework\AssertionFailedError;

it('swaps the container binding to CancellationTokenFake', function () {
    CancellationToken::fake();

    $resolved = app(CancellationTokenContract::class);

    expect($resolved)->toBeInstanceOf(CancellationTokenFake::class);
});

it('asserts a token was created for a cancellable', function () {
    CancellationToken::fake();

    $booking = new TestBooking;
    $booking->id = 1;

    $user = new TestUser;
    $user->id = 1;

    CancellationToken::create(cancellable: $booking, tokenable: $user);

    CancellationToken::assertTokenCreatedFor($booking);
});

it('asserts a token was created for a cancellable and tokenable', function () {
    CancellationToken::fake();

    $booking = new TestBooking;
    $booking->id = 1;

    $user = new TestUser;
    $user->id = 1;

    CancellationToken::create(cancellable: $booking, tokenable: $user);

    CancellationToken::assertTokenCreatedFor($booking, $user);
});

it('fails assertTokenCreatedFor when no token created for that cancellable', function () {
    CancellationToken::fake();

    $booking = new TestBooking;
    $booking->id = 1;

    CancellationToken::assertTokenCreatedFor($booking);
})->throws(AssertionFailedError::class);

it('fails assertTokenCreatedFor when cancellable matches but tokenable does not', function () {
    CancellationToken::fake();

    $booking = new TestBooking;
    $booking->id = 1;

    $user = new TestUser;
    $user->id = 1;

    $otherUser = new TestUser;
    $otherUser->id = 2;

    CancellationToken::create(cancellable: $booking, tokenable: $user);

    CancellationToken::assertTokenCreatedFor($booking, $otherUser);
})->throws(AssertionFailedError::class);

it('asserts a token was consumed', function () {
    CancellationToken::fake();

    $booking = new TestBooking;
    $booking->id = 1;

    $user = new TestUser;
    $user->id = 1;

    $token = CancellationToken::create(cancellable: $booking, tokenable: $user);
    CancellationToken::consume($token);

    CancellationToken::assertTokenConsumed($token);
});

it('fails assertTokenConsumed when token has not been consumed', function () {
    CancellationToken::fake();

    $booking = new TestBooking;
    $booking->id = 1;

    $user = new TestUser;
    $user->id = 1;

    $token = CancellationToken::create(cancellable: $booking, tokenable: $user);

    CancellationToken::assertTokenConsumed($token);
})->throws(AssertionFailedError::class);

it('asserts a token was not consumed', function () {
    CancellationToken::fake();

    $booking = new TestBooking;
    $booking->id = 1;

    $user = new TestUser;
    $user->id = 1;

    $token = CancellationToken::create(cancellable: $booking, tokenable: $user);

    CancellationToken::assertTokenNotConsumed($token);
});

it('asserts no tokens were created', function () {
    CancellationToken::fake();

    CancellationToken::assertNoTokensCreated();
});

it('fails assertNoTokensCreated when tokens were created', function () {
    CancellationToken::fake();

    $booking = new TestBooking;
    $booking->id = 1;

    $user = new TestUser;
    $user->id = 1;

    CancellationToken::create(cancellable: $booking, tokenable: $user);

    CancellationToken::assertNoTokensCreated();
})->throws(AssertionFailedError::class);

it('returns a new fake instance each call ensuring clean state', function () {
    $fake1 = CancellationToken::fake();

    $booking = new TestBooking;
    $booking->id = 1;

    $user = new TestUser;
    $user->id = 1;

    $fake1->create(cancellable: $booking, tokenable: $user);

    $fake2 = CancellationToken::fake();
    $fake2->assertNoTokensCreated();
});
