<?php

namespace Foxen\CancellationToken\Tests\Fixtures;

use Foxen\CancellationToken\Traits\HasCancellationTokens;
use Illuminate\Database\Eloquent\Model;

class TestBooking extends Model
{
    use HasCancellationTokens;

    protected $table = 'test_bookings';

    protected $guarded = ['*'];

    public $timestamps = false;
}
