<?php

namespace Foxen\CancellationToken\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string $token
 * @property string $tokenable_type
 * @property int $tokenable_id
 * @property string $cancellable_type
 * @property int $cancellable_id
 * @property Carbon|null $expires_at
 * @property Carbon|null $used_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Model $tokenable
 * @property-read Model $cancellable
 */
class CancellationToken extends Model
{
    use HasFactory;
    use Prunable;

    /**
     * All columns are guarded — the service layer controls data explicitly.
     * This prevents mass-assignment vulnerabilities on sensitive columns.
     *
     * @var array<int, string>
     */
    protected $guarded = ['*'];

    /**
     * Get the table name from configuration.
     */
    public function getTable(): string
    {
        return config('cancellation-tokens.table') ?: 'cancellation_tokens';
    }

    /**
     * Get the actor (who may cancel) via polymorphic relationship.
     */
    public function tokenable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the subject (what can be cancelled) via polymorphic relationship.
     */
    public function cancellable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    /**
     * Get the prunable model query.
     * Tokens are pruned when expired OR consumed.
     */
    public function prunable(): Builder
    {
        return static::where('expires_at', '<', now())
            ->orWhereNotNull('used_at');
    }
}
