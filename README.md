# Laravel Cancellation Tokens

[![Latest Version on Packagist](https://img.shields.io/packagist/v/foxen/laravel-cancellation-tokens.svg?style=flat-square)](https://packagist.org/packages/foxen/laravel-cancellation-tokens)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/foxen/laravel-cancellation-tokens/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/foxen/laravel-cancellation-tokens/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/foxen/laravel-cancellation-tokens.svg?style=flat-square)](https://packagist.org/packages/foxen/laravel-cancellation-tokens)

A focused Laravel package that manages the full cancellation token lifecycle — generation, storage, verification, expiry, and consumption — so you never hand-roll this system again.

Provides cryptographically secure, single-use, time-limited tokens for cancellable workflows (bookings, orders, subscriptions) **without requiring login**. The plain-text token is returned once for embedding in a URL; only an HMAC-SHA256 hash is ever stored.

## Installation

```bash
composer require foxen/laravel-cancellation-tokens
```

Publish the migration and config:

```bash
php artisan vendor:publish --tag="cancellation-tokens-migrations"
php artisan vendor:publish --tag="cancellation-tokens-config"
php artisan migrate
```

Add a hash key to your `.env` file. This key is used for HMAC-SHA256 token hashing and **must** be set before creating or verifying tokens:

```dotenv
CANCELLATION_TOKEN_HASH_KEY=your-secret-key-here
```

> **Important:** Generate a strong, random value. You can use `php -r "echo base64_encode(random_bytes(32));"` to generate one. This key is separate from `APP_KEY` and should not be shared with it.

## Configuration

The published config file at `config/cancellation-tokens.php`:

```php
return [
    'table'          => 'cancellation_tokens',  // Database table name
    'prefix'         => 'ct_',                  // Token prefix (e.g. ct_a1b2c3...)
    'default_expiry' => 10080,                  // Minutes until expiry (7 days)
    'hash_key'       => env('CANCELLATION_TOKEN_HASH_KEY'),
];
```

## Basic Usage

A complete booking cancellation flow using the `HasCancellationTokens` trait.

### 1. Add the trait to your cancellable model

```php
use Foxen\CancellationToken\Traits\HasCancellationTokens;

class Booking extends Model
{
    use HasCancellationTokens;
}
```

### 2. Create a token and send it

When a booking is confirmed, generate a cancellation token and include it in the confirmation email:

```php
$plainToken = $booking->createCancellationToken($user);

// Embed in a URL — the route only needs the token
$url = url('/booking/cancel/' . $plainToken);

// Send email containing $url...
```

The token is prefixed automatically (e.g. `ct_a1B2c3...`, 67 characters). Only the HMAC-SHA256 hash is stored in the database — the plain-text value is returned **exactly once**.

> **Note:** Creating a new token for the same booking/user pair automatically removes any previous unused tokens for that pair.

### 3. Handle the cancellation request

```php
use Foxen\CancellationToken\Facades\CancellationToken;
use Foxen\CancellationToken\Exceptions\TokenVerificationException;

Route::get('/booking/cancel/{token}', function (string $token) {
    try {
        $cancellationToken = CancellationToken::consume($token);

        // Access the associated models
        $booking = $cancellationToken->cancellable;
        $user = $cancellationToken->tokenable;

        // Perform the cancellation
        $booking->cancel();

        return view('booking.cancelled');
    } catch (TokenVerificationException $e) {
        // $e->reason is a TokenVerificationFailure enum:
        //   - NotFound  — token doesn't exist
        //   - Expired   — token has passed its expiry time
        //   - Consumed  — token has already been used
        return match ($e->reason) {
            TokenVerificationFailure::Expired => response('This cancellation link has expired.'),
            TokenVerificationFailure::Consumed => response('This booking has already been cancelled.'),
            TokenVerificationFailure::NotFound => response('Invalid cancellation link.'),
        };
    }
});
```

`consume()` verifies the token and marks it as used in a single call (`used_at` is set). You can also call `verify()` to check a token without consuming it:

```php
$token = CancellationToken::verify($plainToken);
// $token->cancellable — the booking
// $token->tokenable — the user who requested cancellation
// Token is NOT consumed yet
```

### 4. Validate tokens in form requests

For cancellation via form submission, use the `ValidCancellationToken` validation rule:

```php
use Foxen\CancellationToken\Rules\ValidCancellationToken;

class CancelBookingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', new ValidCancellationToken],
        ];
    }
}
```

If validation fails, the rule stores the failure reason on itself. You can access it after validation to customise your response:

```php
use Foxen\CancellationToken\Rules\ValidCancellationToken;

$rule = new ValidCancellationToken;

// After validation, inspect the failure reason:
$rule->failureReason; // TokenVerificationFailure enum or null
```

## Using the Facade

When you don't want to add the trait to a model — or you need to create tokens across arbitrary model types — use the Facade directly:

```php
use Foxen\CancellationToken\Facades\CancellationToken;
use Carbon\Carbon;

// Create with default expiry (7 days)
$token = CancellationToken::create($subscription, $admin);

// Create with custom expiry
$token = CancellationToken::create($order, $customer, Carbon::now()->addHours(24));

// Verify without consuming
$cancellationToken = CancellationToken::verify($token);

// Verify and consume (single-use)
$cancellationToken = CancellationToken::consume($token);
```

The `create()` method accepts three arguments:
- **`$cancellable`** — the model being cancelled (e.g. `Booking`, `Subscription`, `Order`)
- **`$tokenable`** — the actor who may cancel (e.g. `User`, `Customer`, any model)
- **`$expiresAt`** *(optional)* — a `Carbon` instance; defaults to the configured `default_expiry`

Both `$cancellable` and `$tokenable` must be persisted models (they must exist in the database).

## Events

The package dispatches events at key points in the token lifecycle. All events carry the `CancellationToken` model as a public `$token` property.

| Event | When it fires |
|-------|--------------|
| `TokenCreated` | After a token is created and persisted |
| `TokenVerified` | After a token is successfully verified |
| `TokenConsumed` | After a token is consumed (marked as used) |
| `TokenExpired` | When an expired token is presented to `verify()` or `consume()` |

> On failure paths (`TokenExpired`), the event fires **before** the `TokenVerificationException` is thrown, so your listeners always run.

### Listening for events

```php
use Foxen\CancellationToken\Events\TokenConsumed;
use Foxen\CancellationToken\Events\TokenExpired;

// In a service provider's boot() method:
protected function boot(): void
{
    Event::listen(TokenConsumed::class, function (TokenConsumed $event) {
        $booking = $event->token->cancellable;
        Log::info("Booking {$booking->id} was cancelled.");
    });

    Event::listen(TokenExpired::class, function (TokenExpired $event) {
        // Alert the user that their cancellation link expired
        $event->token->tokenable->notify(new CancellationLinkExpired(
            $event->token->cancellable
        ));
    });
}
```

## Token Cleanup

The `CancellationToken` model implements Laravel's `Prunable` trait. Tokens are automatically pruned when they are:

- **Expired** — `expires_at` is in the past
- **Consumed** — `used_at` is not null

Schedule the prune command in your `routes/console.php` (or `app/Console/Kernel.php` for older Laravel versions):

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('model:prune', [
    '--model' => \Foxen\CancellationToken\Models\CancellationToken::class,
])->daily();
```

Or prune all prunable models together:

```php
Schedule::command('model:prune')->daily();
```

No custom Artisan commands are needed — the package integrates with Laravel's built-in pruning system.

## Testing

### Unit tests with `CancellationTokenFake`

The fake bypasses the database entirely, making your unit tests fast:

```php
use Foxen\CancellationToken\Facades\CancellationToken;
use Foxen\CancellationToken\Models\CancellationToken;

it('creates a cancellation token for the booking', function () {
    $fake = CancellationToken::fake();

    $booking = Booking::make(['id' => 1]);
    $user = User::make(['id' => 1]);

    $token = CancellationToken::create($booking, $user);

    // Assert the token was created for the right models
    $fake->assertTokenCreatedFor($booking, $user);

    // Or just check the cancellable, ignoring the actor:
    // $fake->assertTokenCreatedFor($booking);
});

it('consumes a token', function () {
    $fake = CancellationToken::fake();

    $booking = Booking::make(['id' => 1]);
    $user = User::make(['id' => 1]);

    $token = CancellationToken::create($booking, $user);
    CancellationToken::consume($token);

    $fake->assertTokenConsumed($token);
});

it('does not create tokens unnecessarily', function () {
    $fake = CancellationToken::fake();

    // No tokens created — assertion passes
    $fake->assertNoTokensCreated();
});
```

The `CancellationTokenFake` also enforces token lifecycle rules — calling `consume()` twice on the same token throws `TokenVerificationException`, just like the real service.

### Feature tests with `CancellationTokenFactory`

For tests that need real database records, use the included factory:

```php
use Foxen\CancellationToken\Models\CancellationToken;

// Create a valid, unexpired token
$token = CancellationToken::factory()->create();

// Create a consumed token
$token = CancellationToken::factory()->consumed()->create();

// Create an expired token
$token = CancellationToken::factory()->expired()->create();

// Associate with specific models
$token = CancellationToken::factory()
    ->for($booking, 'cancellable')
    ->for($user, 'tokenable')
    ->create();
```

Note that the factory creates **database records with hashed token values** — the plain-text token is not available. This is by design: the factory is for setting up test state, not for simulating the full create-verify-consume lifecycle (use the service directly for that).

## Security

This package follows the same token storage approach Laravel uses for password reset tokens:

- **HMAC-SHA256 hashing** — tokens are hashed with a dedicated `hash_key` before storage
- **Plain-text never persisted** — the raw token is returned from `create()` exactly once and never stored, logged, or cached
- **Timing-safe comparison** — `hash_equals()` is used for all hash comparisons
- **64 bytes of entropy** — `Str::random(64)` backed by `random_bytes()`
- **Single-use enforcement** — `used_at` timestamp prevents replay
- **Automatic invalidation** — creating a new token for the same pair removes previous unused tokens

## Credits

- [mrdth](https://github.com/mrdth)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
