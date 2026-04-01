<?php

namespace Foxen\CancellationToken\Traits;

use Carbon\Carbon;
use Foxen\CancellationToken\Contracts\CancellationTokenContract;
use Foxen\CancellationToken\Models\CancellationToken;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasCancellationTokens
{
    public function cancellationTokens(): MorphMany
    {
        return $this->morphMany(CancellationToken::class, 'cancellable');
    }

    public function createCancellationToken(Model $tokenable, ?Carbon $expiresAt = null): string
    {
        return app(CancellationTokenContract::class)->create($this, $tokenable, $expiresAt);
    }
}
