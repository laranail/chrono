<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Facades;

use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\Facade;
use Simtabi\Laranail\Chrono\Core\Enums\Region;
use Simtabi\Laranail\Chrono\Core\Timezone\Collection\TimezoneCollection;
use Simtabi\Laranail\Chrono\Core\Timezone\Query\TimezoneQuery;
use Simtabi\Laranail\Chrono\Core\Timezone\Resolver\Resolution;
use Simtabi\Laranail\Chrono\Core\Timezone\Timezones as TimezonesService;
use Simtabi\Laranail\Chrono\Core\Timezone\Value\Timezone;

/**
 * The timezone surface.
 *
 * Named in the plural so the generated `Core\Enums\Timezone` enum keeps its singular name — the two
 * can then be imported into the same file without an alias, and migrating off
 * `laranail/package-tools`' enum stays a one-line change.
 *
 * @method static Timezone of(mixed $input)
 * @method static Timezone|null tryOf(mixed $input)
 * @method static string resolve(mixed $input)
 * @method static Resolution|null explain(mixed $input)
 * @method static TimezoneCollection candidates(mixed $input)
 * @method static bool has(mixed $input)
 * @method static string canonicalise(string $identifier)
 * @method static TimezoneQuery query()
 * @method static TimezoneCollection all()
 * @method static TimezoneCollection inCountry(string $countryCode)
 * @method static TimezoneCollection inRegion(Region|string $region)
 * @method static Timezone utc()
 * @method static Timezone fallback()
 * @method static Timezone system()
 * @method static array<string, string> aliases()
 * @method static DateTimeImmutable now(mixed $zone = null)
 * @method static DateTimeImmutable convert(DateTimeInterface $instant, mixed $to)
 * @method static string version()
 * @method static string fingerprint()
 *
 * @see TimezonesService
 */
final class Timezones extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return TimezonesService::class;
    }
}
