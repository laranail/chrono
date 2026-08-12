<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Facades;

use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\Facade;
use Psr\Clock\ClockInterface;
use Simtabi\Laranail\Chrono\Core\Config\CatalogueOptions;
use Simtabi\Laranail\Chrono\Core\Config\DstPolicy;
use Simtabi\Laranail\Chrono\Core\Contracts\TimezoneRepository;
use Simtabi\Laranail\Chrono\Core\Enums\AmbiguityPolicy;
use Simtabi\Laranail\Chrono\Core\Enums\GapPolicy;
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
 * @method static TimezoneQuery unrestrictedQuery()
 * @method static DateTimeImmutable now(mixed $zone = null)
 * @method static DateTimeImmutable convert(DateTimeInterface $instant, mixed $to)
 * @method static string version()
 * @method static string fingerprint()
 * @method static bool usesSystemDatabase()
 * @method static TimezonesService withClock(ClockInterface $clock)
 * @method static TimezonesService withRepository(TimezoneRepository $repository)
 * @method static TimezonesService preferring(string ...$countryCodes)
 * @method static TimezonesService withCatalogue(CatalogueOptions $catalogue)
 * @method static TimezonesService withDst(DstPolicy $policy)
 * @method static TimezonesService onGap(GapPolicy $policy)
 * @method static TimezonesService onAmbiguity(AmbiguityPolicy $policy)
 * @method static TimezonesService preservingAliases(bool $preserve = true)
 * @method static TimezonesService lenient()
 * @method static TimezonesService allowingAbbreviations(bool $allow = true)
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
