<?php

use Foxen\CancellationToken\Enums\TokenVerificationFailure;

it('has three cases')
    ->expect(fn () => TokenVerificationFailure::cases())
    ->toHaveCount(3);

it('has NotFound case with correct value')
    ->expect(fn () => TokenVerificationFailure::NotFound->value)
    ->toBe('not_found');

it('has Expired case with correct value')
    ->expect(fn () => TokenVerificationFailure::Expired->value)
    ->toBe('expired');

it('has Consumed case with correct value')
    ->expect(fn () => TokenVerificationFailure::Consumed->value)
    ->toBe('consumed');

it('is a backed enum with string type')
    ->expect(TokenVerificationFailure::class)
    ->toBeBackedEnum('string');
