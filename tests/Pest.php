<?php

declare(strict_types=1);

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
 * Whether this host carries the exact tz release the generated files were built against.
 *
 * Byte-for-byte parity is only a check on the database the files describe — `resources/tzdata-version.txt`,
 * written by the generators. On any other release the comparison reports a difference no commit
 * caused. CI pins the PECL `timezonedb` to that release so these run for real; a laptop whose PHP
 * bundles something else skips them, and `composer sync-check` fails loudly if the CI pin ever stops
 * resolving, so the skip is never a silent hole.
 */
function tzdataIsVersioned(): bool
{
    $expected = trim((string) @file_get_contents(dirname(__DIR__) . '/resources/tzdata-version.txt'));

    return $expected !== '' && $expected === timezone_version_get();
}

/** Zones only carry coordinates and country codes when the host ships the location tables. */
function tzdataHasLocations(): bool
{
    return new DateTimeZone('Africa/Nairobi')->getLocation()['country_code'] === 'KE';
}
