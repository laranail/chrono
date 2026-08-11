<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Facades;

use Illuminate\Support\Facades\Facade;
use Simtabi\Laranail\Chrono\Chrono as ChronoService;
use Simtabi\Laranail\Chrono\Core\Format\DateFormatter;
use Simtabi\Laranail\Chrono\Core\Format\DateParser;
use Simtabi\Laranail\Chrono\Core\Humanize\Humanizer;
use Simtabi\Laranail\Chrono\Core\Timezone\Timezones;
use Simtabi\Laranail\Chrono\Core\Timezone\Value\Timezone;

/**
 * @method static Timezones timezones()
 * @method static DateFormatter format()
 * @method static DateParser parse()
 * @method static Humanizer humanize()
 * @method static Timezone zone(mixed $input)
 * @method static string version()
 *
 * @see ChronoService
 */
final class Chrono extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ChronoService::class;
    }
}
