<?php

declare(strict_types=1);

use Simtabi\Laranail\Chrono\Core\Enums\GapPolicy;
use Simtabi\Laranail\Chrono\Core\Enums\Region;
use Simtabi\Laranail\Chrono\Core\Enums\TimezoneKind;
use Simtabi\Laranail\Chrono\Core\Timezone\Value\Timezone;

it('describes itself', function (): void {
    $nairobi = new Timezone('Africa/Nairobi');

    expect($nairobi->identifier)->toBe('Africa/Nairobi')
        ->and($nairobi->city())->toBe('Nairobi')
        ->and($nairobi->region())->toBe(Region::Africa)
        ->and($nairobi->countryCode())->toBe('KE')
        ->and($nairobi->abbreviation())->toBe('EAT')
        ->and($nairobi)->toHaveOffset('+03:00');
});

it('reads the last segment of a nested identifier as the city', function (): void {
    expect(new Timezone('America/Argentina/Salta')->city())->toBe('Salta');
});

/**
 * Dublin runs *negative* daylight saving: the database marks its winter GMT period as the saving
 * one, so `format('I')` returns '1' in January and '0' in July. That is the opposite of what most
 * people expect, and it is why DST state is read from the transition record rather than inferred
 * from a format character.
 */
it('reports negative daylight saving as the database records it', function (): void {
    $dublin = new Timezone('Europe/Dublin');
    $january = utc('2026-01-15 12:00');
    $july = utc('2026-07-15 12:00');

    expect($dublin->convert($january)->format('I'))->toBe('1')
        ->and($dublin->convert($july)->format('I'))->toBe('0')
        ->and($dublin->isDst($january))->toBeTrue()
        ->and($dublin->isDst($july))->toBeFalse()
        ->and($dublin->abbreviation($january))->toBe('GMT')
        ->and($dublin->abbreviation($july))->toBe('IST');
});

it('never assumes a daylight-saving shift is an hour', function (string $identifier, int $expected): void {
    expect(new Timezone($identifier)->dstSavings()->seconds)->toBe($expected);
})->with('dst savings');

it('lists transitions for a year', function (): void {
    $newYork = new Timezone('America/New_York');

    expect($newYork->transitionsIn(2026)->count())->toBe(2)
        ->and($newYork->transitionsIn(2026)->gaps()->count())->toBe(1)
        ->and($newYork->transitionsIn(2026)->overlaps()->count())->toBe(1)
        ->and($newYork->nextTransition())->not->toBeNull();
});

/** Egypt shifted for Ramadan on top of ordinary daylight saving — four changes in one year. */
it('handles a zone with more than two changes in a year', function (): void {
    expect(new Timezone('Africa/Cairo')->transitionsIn(2014)->count())->toBe(4);
})->group('tzdata');

it('answers daylight-saving questions safely for rule-less zones', function (string $identifier): void {
    $timezone = new Timezone($identifier, TimezoneKind::Fixed);

    expect($timezone->observesDst())->toBeFalse()
        ->and($timezone->transitionsIn(2026)->count())->toBe(0)
        ->and($timezone->nextTransition())->toBeNull();
})->with('rule-less zones');

it('canonicalises a deprecated identifier', function (string $alias, string $canonical): void {
    $timezone = new Timezone($alias, TimezoneKind::Link);

    expect($timezone->canonicalIdentifier())->toBe($canonical)
        ->and($timezone->equals($canonical))->toBeTrue()
        ->and($timezone->isDeprecated())->toBeTrue();
})->with('aliases');

it('resolves wall-clock readings through its own policies', function (): void {
    $newYork = new Timezone('America/New_York');

    expect($newYork->at('2026-03-08 02:30', GapPolicy::Forward)->format('H:i T'))->toBe('03:30 EDT')
        ->and($newYork->inspect('2026-03-08 02:30')->isGap())->toBeTrue()
        ->and($newYork->inspect('2026-11-01 01:30')->isAmbiguous())->toBeTrue();
});

it('measures the difference between two zones at an instant', function (): void {
    $difference = new Timezone('America/New_York')->diff(new Timezone('Africa/Nairobi'), utc('2026-07-15 12:00'));

    expect($difference->format())->toBe('-07:00');
});

/**
 * PHP gives a deprecated identifier the `??`/-90/-180 sentinel rather than a place, so a picker
 * offering legacy spellings used to show half its rows with no country and no flag — for zones that
 * name the same city as a row that had both.
 */
describe('location follows an alias', function (): void {
    it('gives a deprecated zone the country of the zone it points at', function (): void {
        $calcutta = new Timezone('Asia/Calcutta', TimezoneKind::Link);
        $kolkata = new Timezone('Asia/Kolkata');

        expect($calcutta->countryCode())->toBe('IN')
            ->and($calcutta->location()?->latitude)->toBe($kolkata->location()?->latitude);
    });

    it('still returns nothing for a zone that is not a place', function (): void {
        // `EST` and `Etc/GMT+5` are rules, not locations, and no alias leads anywhere better.
        expect(new Timezone('EST', TimezoneKind::Legacy)->location())->toBeNull()
            ->and(new Timezone('Etc/GMT+5', TimezoneKind::Fixed)->location())->toBeNull()
            ->and(new Timezone('UTC', TimezoneKind::Fixed)->location())->toBeNull();
    });
});
