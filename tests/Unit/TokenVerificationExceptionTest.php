<?php

use Foxen\CancellationToken\Enums\TokenVerificationFailure;
use Foxen\CancellationToken\Exceptions\TokenVerificationException;

it('carries a reason property')
    ->expect(fn () => new TokenVerificationException(TokenVerificationFailure::NotFound))
    ->reason->toBe(TokenVerificationFailure::NotFound);

it('reason is accessible and returns correct enum case')
    ->expect(fn () => new TokenVerificationException(TokenVerificationFailure::Expired))
    ->reason->toBe(TokenVerificationFailure::Expired);

it('has a generic message for NotFound')
    ->expect(fn () => new TokenVerificationException(TokenVerificationFailure::NotFound))
    ->getMessage()->toBe('Token verification failed');

it('has a generic message for Expired')
    ->expect(fn () => new TokenVerificationException(TokenVerificationFailure::Expired))
    ->getMessage()->toBe('Token verification failed');

it('has a generic message for Consumed')
    ->expect(fn () => new TokenVerificationException(TokenVerificationFailure::Consumed))
    ->getMessage()->toBe('Token verification failed');

it('has a public readonly reason property', function () {
    $ref = new ReflectionProperty(TokenVerificationException::class, 'reason');

    expect($ref->isPublic())->toBeTrue()
        ->and($ref->isReadOnly())->toBeTrue();
});

it('extends Exception')
    ->expect(TokenVerificationException::class)
    ->toExtend(Exception::class);
