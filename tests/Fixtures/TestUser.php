<?php

namespace Foxen\CancellationToken\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class TestUser extends Model
{
    protected $table = 'test_users';

    protected $guarded = ['*'];

    public $timestamps = false;
}
