<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Simtabi\Laranail\Chrono\Core\Timezone\Value\Timezone;
use Simtabi\Laranail\Chrono\Models\Concerns\HasTimezone;

/**
 * A model using `HasTimezone` and declaring nothing else — no cast, no accessor, no scope.
 *
 * That is the whole claim the trait makes, so the fixture has to be this bare for the tests to mean
 * anything.
 *
 * @property Timezone|null $timezone
 */
final class TimezonedUser extends Model
{
    use HasTimezone;

    protected $table = 'chrono_test_users';

    protected $guarded = [];

    public $timestamps = false;
}
