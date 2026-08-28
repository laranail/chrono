<?php

declare(strict_types=1);

use Simtabi\Laranail\Chrono\Core\Timezone\Timezones;
use Simtabi\Laranail\Chrono\Core\Testing\FrozenClock;
use Simtabi\Laranail\Chrono\Core\Timezone\Value\Timezone;
use Simtabi\Laranail\Chrono\Core\Timezone\Repository\PhpTimezoneRepository;

/*
|--------------------------------------------------------------------------
| Determinism
|--------------------------------------------------------------------------
|
| For a package whose subject is daylight saving, reading the system clock
| directly would mean its own answers changed twice a year in ways no test
| could pin down. Every "now" comes from an injected PSR-20 clock instead.
|
*/

beforeEach(function (): void {
    $this->winter = new FrozenClock('2026-01-15T12:00:00Z');
    $this->summer = new FrozenClock('2026-07-15T12:00:00Z');
});

it('answers according to the clock it was given', function (): void {
    $winter = new Timezone('America/New_York', clock: $this->winter);
    $summer = new Timezone('America/New_York', clock: $this->summer);

    expect($winter->offset()->format())->toBe('-05:00')
        ->and($summer->offset()->format())->toBe('-04:00')
        ->and($winter->abbreviation())->toBe('EST')
        ->and($summer->abbreviation())->toBe('EDT')
        ->and($winter->isDst())->toBeFalse()
        ->and($summer->isDst())->toBeTrue();
});

it('gives the same answer every time it is asked', function (): void {
    $timezone = new Timezone('America/New_York', clock: $this->winter);
    $again = new Timezone('America/New_York', clock: new FrozenClock('2026-01-15T12:00:00Z'));

    expect($timezone->offset()->seconds)->toBe($again->offset()->seconds)
        ->and($timezone->nextTransition()?->at->format('Y-m-d'))->toBe('2026-03-08')
        ->and($timezone->previousTransition()?->at->format('Y-m-d'))->toBe('2025-11-02');
});

it('reaches every zone the entry point and the query build', function (): void {
    $winter = (new Timezones)->withClock($this->winter);
    $summer = (new Timezones)->withClock($this->summer);

    expect($winter->of('America/New_York')->offset()->format())->toBe('-05:00')
        ->and($winter->query()->only('America/New_York')->first()?->offset()->format())->toBe('-05:00')
        ->and($summer->query()->only('America/New_York')->first()?->offset()->format())->toBe('-04:00');
});

/**
 * The fingerprint keys every cache entry, so it must change when tzdata changes and at no other
 * time. Sampling transitions in a window measured from `time()` looks reasonable and is a slow
 * leak: transitions drift in and out as days pass, rotating the key space against an unchanged
 * database. The window is anchored to fixed dates instead.
 */
it('fingerprints the database, not the moment it was asked', function (): void {
    $first = (new PhpTimezoneRepository)->fingerprint();
    $second = (new PhpTimezoneRepository)->fingerprint();

    expect($first)->toBe($second)->toHaveLength(12);
});
