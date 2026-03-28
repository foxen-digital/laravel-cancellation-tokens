<?php

namespace Foxen\CancellationToken\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

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
 */
class CancellationToken extends Model
{
    protected $table = 'cancellation_tokens';

    protected $fillable = [
        'token',
        'tokenable_type',
        'tokenable_id',
        'cancellable_type',
        'cancellable_id',
        'expires_at',
        'used_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];
}
