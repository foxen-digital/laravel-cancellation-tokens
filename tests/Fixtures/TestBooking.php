<?php

namespace Foxen\CancellationToken\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class TestBooking extends Model
{
    protected $table = 'test_bookings';

    protected $guarded = ['*'];

    public $timestamps = false;
}
