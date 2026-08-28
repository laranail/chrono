<?php

declare(strict_types=1);

use Simtabi\Laranail\Chrono\Core\Enums\Region;
use Simtabi\Laranail\Chrono\Core\Enums\SelectShape;
use Simtabi\Laranail\Chrono\Core\Enums\TimezoneField;
use Simtabi\Laranail\Chrono\Core\Timezone\Value\Timezone;
use Simtabi\Laranail\Chrono\Core\Timezone\Query\TimezoneQuery;
use Simtabi\Laranail\Chrono\Core\Timezone\Repository\PhpTimezoneRepository;

beforeEach(function (): void {
    $this->query = new TimezoneQuery(new PhpTimezoneRepository);
    $this->july = utc('2026-07-15 12:00');
});

it('never mutates the query it was called on', function (): void {
    $base = $this->query->inCountry('KE');
    $branched = $base->inCountry('TZ');

    expect($base->count())->toBe(1)
        ->and($branched->count())->toBe(2);
});

it('excludes aliases and Etc zones by default', function (): void {
    expect($this->query->count())->toBe(count(DateTimeZone::listIdentifiers(DateTimeZone::ALL)))
        ->and($this->query->identifiers())->not->toContain('Asia/Calcutta')
        ->and($this->query->identifiers())->not->toContain('Etc/GMT+5');
});

it('filters', function (): void {
    expect($this->query->inCountry('KE')->identifiers())->toBe(['Africa/Nairobi'])
        ->and($this->query->matching('nairobi')->identifiers())->toBe(['Africa/Nairobi'])
        ->and($this->query->only('UTC', 'Africa/Nairobi')->count())->toBe(2)
        ->and($this->query->inRegion(Region::Africa)->except('Africa/Nairobi')->identifiers())
        ->not->toContain('Africa/Nairobi')
        ->and($this->query->includeUtc(false)->identifiers())->not->toContain('UTC')
        ->and($this->query->where(fn (Timezone $t): bool => $t->countryCode() === 'KE')->count())->toBe(1);
});

it('filters on daylight-saving behaviour', function (): void {
    expect($this->query->observingDst(false)->identifiers())->not->toContain('America/New_York')
        ->and($this->query->observingDst(true)->identifiers())->toContain('America/New_York');
});

it('filters on offset at a chosen instant', function (): void {
    expect($this->query->withOffset('+03:00')->asOf($this->july)->identifiers())->toContain('Africa/Nairobi')
        ->and($this->query->offsetBetween('+01:00', '+04:00')->asOf($this->july)->count())->toBeGreaterThan(20);
});

it('orders by any field, stably', function (): void {
    $offsets = $this->query->inRegion(Region::Africa)->orderByOffset()->asOf($this->july)
        ->get()->pluck(TimezoneField::Offset, $this->july);

    $sorted = $offsets;
    sort($sorted);

    expect($offsets)->toBe($sorted);
});

/**
 * SQL builders traditionally have COUNT ignore LIMIT. Here that would mean `take(5)->count()`
 * returning 419 while `take(5)->get()->count()` returns 5 — a difference nobody expects.
 */
it('counts what it would return', function (): void {
    expect($this->query->take(5)->count())->toBe(5)
        ->and($this->query->take(5)->get()->count())->toBe(5)
        ->and($this->query->orderByIdentifier()->skip(2)->take(3)->get()->count())->toBe(3);
});

it('answers without materialising the whole catalogue', function (): void {
    expect($this->query->inCountry('KE')->first()?->identifier)->toBe('Africa/Nairobi')
        ->and($this->query->inCountry('KE')->exists())->toBeTrue()
        ->and($this->query->matching('zzzznope')->exists())->toBeFalse();

    $seen = 0;

    foreach ($this->query->inRegion(Region::Europe)->lazy() as $ignored) {
        $seen++;

        if ($seen >= 3) {
            break;
        }
    }

    expect($seen)->toBe(3);
});

it('groups', function (): void {
    expect($this->query->inRegion(Region::Africa)->groupBy(TimezoneField::Region))->toHaveCount(1)
        ->and(count($this->query->inRegion(Region::Europe)->groupBy(TimezoneField::Country)))->toBeGreaterThan(20);
});

describe('select shapes', function (): void {
    it('renders the three shapes pheg produced, byte for byte', function (SelectShape $shape, array $expected): void {
        expect($this->query->inCountry('KE')->toSelectOptions($shape))->toBe($expected);
    })->with([
        [SelectShape::Flat, ['Africa/Nairobi' => 'Nairobi, KE (UTC +03:00)']],
        [SelectShape::Grouped, ['Africa' => ['Africa/Nairobi' => 'Nairobi (UTC +03:00)']]],
        [SelectShape::Formed, ['Africa'  => ['Africa/Nairobi' => 'Africa/Nairobi (UTC +03:00)']]],
    ]);

    it('renders a payload a JavaScript picker can use directly', function (): void {
        $payload = $this->query->inCountry('KE')->toSelectOptions(SelectShape::Payload);

        expect($payload)->toBeArray()
            ->and(array_is_list($payload))->toBeTrue()
            ->and($payload[0]['id'])->toBe('Africa/Nairobi')
            ->and($payload[0]['search'])->toContain('nairobi')
            ->and($payload[0]['dir'])->toBe('ltr');
    });

    it('marks text direction for right-to-left locales', function (): void {
        $payload = $this->query->inCountry('KE')->toSelectOptions(SelectShape::Payload, rtl: true);

        expect($payload[0]['dir'])->toBe('rtl');
    });
});
