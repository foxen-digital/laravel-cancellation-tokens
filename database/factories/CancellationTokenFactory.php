<?php

namespace Foxen\CancellationToken\Database\Factories;

use Foxen\CancellationToken\Models\CancellationToken;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CancellationTokenFactory extends Factory
{
    protected $model = CancellationToken::class;

    public function definition(): array
    {
        $plainToken = config('cancellation-tokens.prefix', 'ct_').Str::random(64);

        $key = config('cancellation-tokens.hash_key');

        if ($key === null || trim($key) === '') {
            throw new \RuntimeException(
                'A hash key must be configured via cancellation-tokens.hash_key before tokens can be created or verified.'
            );
        }

        return [
            'token' => hash_hmac('sha256', $plainToken, $key),
            'tokenable_type' => 'App\Models\User',
            'tokenable_id' => 1,
            'cancellable_type' => 'App\Models\Booking',
            'cancellable_id' => 1,
            'expires_at' => now()->addDays(7),
            'used_at' => null,
        ];
    }

    public function newModel(array $attributes = []): CancellationToken
    {
        return (new CancellationToken)->forceFill($attributes);
    }

    public function consumed(): static
    {
        return $this->state(fn (array $attributes) => [
            'used_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDays(7),
        ]);
    }
}
