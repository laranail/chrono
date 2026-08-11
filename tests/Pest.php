<?php

declare(strict_types=1);

use Simtabi\Laranail\Chrono\Core\Timezone\Repository\PhpTimezoneRepository;
use Simtabi\Laranail\Chrono\Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test case bindings
|--------------------------------------------------------------------------
|
| Only Feature tests boot Laravel. Unit tests exercise src/Core directly with
| no container, which is the cheapest proof that the core stayed framework-free
| — a stray Illuminate dependency there fails the test run, not just deptrac.
|
*/

uses(TestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeIanaIdentifier', function (): void {
    expect(DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC))
        ->toContain($this->value);
});

expect()->extend('toHaveOffset', function (string $formatted): void {
    expect($this->value->offset()->format())->toBe($formatted);
});

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function utc(string $expression = 'now'): DateTimeImmutable
{
    return new DateTimeImmutable($expression, new DateTimeZone('UTC'));
}

function zone(string $identifier): DateTimeZone
{
    return new DateTimeZone($identifier);
}

/**
 * Whether PHP carries its own versioned tz database rather than reading the operating system's.
 *
 * Built `--with-system-tzdata` — the official Docker images, Debian, Ubuntu, and therefore most CI —
 * `timezone_version_get()` returns the literal `0.system`. The data is fine, often fresher than the
 * bundled copy, but it has no release to compare against, so a byte-for-byte check of data generated
 * on another machine is not a check. It is noise that turns CI red for a reason no commit caused.
 *
 * Drift is still caught: `tzdata.yml` runs weekly and opens an issue.
 */
function tzdataIsVersioned(): bool
{
    return (new PhpTimezoneRepository)
        ->usesSystemDatabase() === false;
}

/** Zones only carry coordinates and country codes when the host ships the location tables. */
function tzdataHasLocations(): bool
{
    return (new DateTimeZone('Africa/Nairobi'))->getLocation()['country_code'] === 'KE';
}
