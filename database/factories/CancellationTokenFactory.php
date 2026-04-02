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

        return [
            'token' => hash_hmac('sha256', $plainToken, config('app.key')),
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
