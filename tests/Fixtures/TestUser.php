<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Simtabi\Laranail\Chrono\Casts\AsTimezone;

final class TestUser extends Model
{
    protected $table = 'chrono_test_users';

    protected $guarded = [];

    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['timezone' => AsTimezone::class];
    }
}
