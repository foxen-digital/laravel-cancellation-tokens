<?php

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray'])
    ->each->not->toBeUsed();

// AC 1: Testing namespace isolation
arch('CancellationTokenFake is only used in tests and the Facade')
    ->expect('Foxen\CancellationToken\Testing\CancellationTokenFake')
    ->toOnlyBeUsedIn([
        'Foxen\CancellationToken\Tests',
        'Foxen\CancellationToken\Facades',
    ]);

// AC 2: Token comparison safety
arch('token service uses HMAC-SHA256 hashing')
    ->expect('Foxen\CancellationToken\CancellationTokenService')
    ->toUse('hash_hmac');

arch('src does not use bcrypt or password_hash')
    ->expect('Foxen\CancellationToken')
    ->not->toUse(['bcrypt', 'password_hash']);

// AC 3: Plain-text token containment
arch('src does not use logging')
    ->expect('Foxen\CancellationToken')
    ->not->toUse([
        'Illuminate\Support\Facades\Log',
        'Psr\Log\LoggerInterface',
        'logger',
    ]);

arch('src does not use cache')
    ->expect('Foxen\CancellationToken')
    ->not->toUse([
        'Illuminate\Support\Facades\Cache',
        'Illuminate\Contracts\Cache\Repository',
        'cache',
    ]);

arch('service implements the contract')
    ->expect('Foxen\CancellationToken\CancellationTokenService')
    ->toImplement('Foxen\CancellationToken\Contracts\CancellationTokenContract');

arch('fake implements the contract')
    ->expect('Foxen\CancellationToken\Testing\CancellationTokenFake')
    ->toImplement('Foxen\CancellationToken\Contracts\CancellationTokenContract');

// AC 4: Pest test conventions
arch('test files do not define setUp method')
    ->expect('Foxen\CancellationToken\Tests')
    ->not->toHaveMethod('setUp')
    ->ignoring('Foxen\CancellationToken\Tests\TestCase');

// Deferred: No custom Artisan commands
arch('no custom artisan commands exist')
    ->expect('Foxen\CancellationToken')
    ->not->toExtend('Illuminate\Console\Command');

// Deferred: No SoftDeletes
arch('CancellationToken model does not use SoftDeletes')
    ->expect('Foxen\CancellationToken\Models\CancellationToken')
    ->not->toUse('Illuminate\Database\Eloquent\SoftDeletes');

// Deferred: Events are readonly
arch('events are readonly classes')
    ->expect('Foxen\CancellationToken\Events')
    ->classes()
    ->toBeReadonly();
