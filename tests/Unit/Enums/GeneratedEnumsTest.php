<?php

declare(strict_types=1);

use Simtabi\Laranail\Chrono\Core\Enums\Timezone as TimezoneEnum;
use Simtabi\Laranail\Chrono\Core\Enums\TimezoneAbbreviation;
use Simtabi\Laranail\Chrono\Core\Enums\TimezoneKind;
use Simtabi\Laranail\Chrono\Core\Enums\TimezoneLegacy;

/*
|--------------------------------------------------------------------------
| Parity with the live database
|--------------------------------------------------------------------------
|
| These enums are generated, so the only interesting question is whether they
| still describe the tz database the machine is actually running. Checked in
| both directions: a missing case and a stale case are different bugs and both
| matter.
|
*/

it('has a case for every canonical identifier, and no others', function (): void {
    $cases = array_map(static fn (TimezoneEnum $c): string => $c->value, TimezoneEnum::cases());
    $live = DateTimeZone::listIdentifiers(DateTimeZone::ALL);

    expect(array_diff($cases, $live))->toBe([])
        ->and(array_diff($live, $cases))->toBe([])
        ->and($cases)->toHaveCount(count($live));
});

it('covers exactly the backward-compatible remainder', function (): void {
    $cases = array_map(static fn (TimezoneLegacy $c): string => $c->value, TimezoneLegacy::cases());
    $live = array_values(array_diff(
        DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC),
        DateTimeZone::listIdentifiers(DateTimeZone::ALL),
    ));

    expect(array_diff($cases, $live))->toBe([])
        ->and(array_diff($live, $cases))->toBe([]);
});

it('has a case for every abbreviation', function (): void {
    $cases = array_map(static fn (TimezoneAbbreviation $c): string => strtolower($c->value), TimezoneAbbreviation::cases());

    expect(array_diff(array_keys(DateTimeZone::listAbbreviations()), $cases))->toBe([]);
});

/*
|--------------------------------------------------------------------------
| Behaviour, which lives in a hand-written concern
|--------------------------------------------------------------------------
*/

it('classifies each identifier correctly', function (): void {
    expect(TimezoneEnum::AfricaNairobi->kind())->toBe(TimezoneKind::Canonical)
        ->and(TimezoneEnum::Utc->kind())->toBe(TimezoneKind::Fixed)
        ->and(TimezoneLegacy::from('Asia/Calcutta')->kind())->toBe(TimezoneKind::Link)
        ->and(TimezoneLegacy::from('Etc/GMT+5')->kind())->toBe(TimezoneKind::Fixed)
        ->and(TimezoneLegacy::from('CET')->kind())->toBe(TimezoneKind::Legacy);
});

it('follows an alias to its canonical target', function (): void {
    expect(TimezoneLegacy::from('Asia/Calcutta')->canonical())->toBe('Asia/Kolkata')
        // Etc zones are not aliases: they have no canonical target and stand for themselves.
        ->and(TimezoneLegacy::from('Etc/GMT+5')->canonical())->toBe('Etc/GMT+5');
});

it('converts to the value objects', function (): void {
    expect(TimezoneEnum::AsiaTokyo->toDateTimeZone()->getName())->toBe('Asia/Tokyo')
        ->and(TimezoneEnum::AfricaNairobi->toTimezone()->offset()->format())->toBe('+03:00')
        ->and(TimezoneEnum::AfricaNairobi->city())->toBe('Nairobi');
});

/**
 * An abbreviation looks like an identifier and is not: 96 of the 144 map to several zones. Anything
 * that stores one as a timezone has already lost information, so the enum makes that visible.
 */
it('surfaces how ambiguous an abbreviation is', function (): void {
    expect(TimezoneAbbreviation::from('CST')->isAmbiguous())->toBeTrue()
        ->and(count(TimezoneAbbreviation::from('CST')->identifiers()))->toBeGreaterThan(50)
        ->and(TimezoneAbbreviation::from('CST')->offsetSeconds())->toBeNull()
        ->and(TimezoneAbbreviation::from('EAT')->offsetSeconds())->toBe(10800)
        ->and(TimezoneAbbreviation::from('EDT')->isDaylightSaving())->toBeTrue()
        ->and(TimezoneAbbreviation::from('EAT')->isDaylightSaving())->toBeFalse();
});

/** Case names match laranail/package-tools exactly, so migrating is a one-line `use` change. */
it('keeps the case names its predecessor used', function (): void {
    expect(TimezoneEnum::AfricaNairobi->value)->toBe('Africa/Nairobi')
        ->and(TimezoneEnum::AmericaNewYork->value)->toBe('America/New_York')
        ->and(TimezoneEnum::Utc->value)->toBe('UTC');
});
