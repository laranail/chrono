<?php

declare(strict_types=1);

use Simtabi\Laranail\Chrono\Core\Enums\Region;
use Simtabi\Laranail\Chrono\Core\Enums\Timezone as TimezoneEnum;
use Simtabi\Laranail\Chrono\Core\Enums\TimezoneAbbreviation;
use Simtabi\Laranail\Chrono\Core\Enums\TimezoneLegacy;
use Simtabi\Laranail\Chrono\Core\Enums\Tz;
use Simtabi\Laranail\Chrono\Core\Exception\TimezoneNotFound;
use Simtabi\Laranail\Chrono\Core\Testing\FrozenClock;
use Simtabi\Laranail\Chrono\Core\Timezone\Timezones;

beforeEach(function (): void {
    $this->timezones = new Timezones;
});

it('works with no configuration at all', function (mixed $input, string $expected): void {
    expect($this->timezones->of($input)->identifier)->toBe($expected);
})->with('resolvable inputs');

it('throws with context when nothing resolves', function (): void {
    try {
        (void) $this->timezones->of('not a zone');
        $this->fail('Expected TimezoneNotFound.');
    } catch (TimezoneNotFound $e) {
        expect($e->context())->toHaveKey('input');
    }
});

it('returns null rather than throwing when asked politely', function (): void {
    expect($this->timezones->tryOf('nope nope'))->toBeNull()
        ->and($this->timezones->has('Europe/Berlin'))->toBeTrue();
});

it('falls back instead of throwing when lenient', function (): void {
    expect($this->timezones->lenient()->resolve('not a zone'))->toBe('UTC');
});

it('offers candidates rather than guessing', function (): void {
    expect($this->timezones->tryOf('US'))->toBeNull()
        ->and($this->timezones->lenient()->candidates('US')->count())->toBeGreaterThan(10)
        ->and($this->timezones->preferring('US')->allowingAbbreviations()->of('CST')->identifier)
        ->toBe('America/Chicago');
});

it('exposes the catalogue', function (): void {
    expect($this->timezones->all()->count())->toBe(count(DateTimeZone::listIdentifiers(DateTimeZone::ALL)))
        ->and($this->timezones->inCountry('KE')->identifiers())->toBe(['Africa/Nairobi'])
        ->and($this->timezones->inRegion(Region::Africa)->count())->toBeGreaterThan(50)
        ->and($this->timezones->utc()->identifier)->toBe('UTC')
        ->and($this->timezones->canonicalise('US/Eastern'))->toBe('America/New_York');
});

it('takes its time from an injected clock', function (): void {
    $frozen = $this->timezones->withClock(new FrozenClock('2026-06-15T12:00:00Z'));

    expect($frozen->now()->format('c'))->toBe('2026-06-15T12:00:00+00:00')
        ->and($frozen->now('Africa/Nairobi')->format('H:i'))->toBe('15:00')
        ->and($frozen->now()->getTimestamp())->toBe($frozen->now()->getTimestamp());
});

it('converts an instant into another zone without changing it', function (): void {
    $instant = utc('2026-06-15 12:00');
    $tokyo = $this->timezones->convert($instant, 'Asia/Tokyo');

    expect($tokyo->format('H:i'))->toBe('21:00')
        ->and($tokyo->getTimestamp())->toBe($instant->getTimestamp());
});

it('reads the process default without ever setting it', function (): void {
    $before = date_default_timezone_get();

    $this->timezones->system();

    expect(date_default_timezone_get())->toBe($before);
});

/**
 * The static helper and the enums are only useful if the service accepts them directly. It does,
 * by unwrapping to the string they spell and letting the chain judge that — never by trusting the
 * wrapper, which is how `CST` would come to name one zone instead of sixty-two.
 */
describe('typed inputs', function (): void {
    it('accepts an identifier enum case', function (): void {
        expect($this->timezones->of(TimezoneEnum::AmericaNewYork)->identifier)->toBe('America/New_York');
    });

    it('accepts a Tz constant, which is only a string', function (): void {
        expect($this->timezones->of(Tz::AMERICA_NEW_YORK)->identifier)->toBe('America/New_York');
    });

    it('canonicalises a legacy enum case like any other alias', function (): void {
        expect($this->timezones->of(TimezoneLegacy::AsiaCalcutta)->identifier)->toBe('Asia/Kolkata');
    });

    it('accepts anything that spells a zone', function (): void {
        $stringable = new class implements Stringable
        {
            public function __toString(): string
            {
                return 'Africa/Nairobi';
            }
        };

        expect($this->timezones->of($stringable)->identifier)->toBe('Africa/Nairobi');
    });

    /**
     * The trap this unwrapping is shaped to avoid. An abbreviation enum spells a string that is not
     * an identifier, so it must still go through the strategy that knows how ambiguous it is — and
     * be refused when the configuration has not enabled that strategy.
     */
    it('does not let an abbreviation enum smuggle itself past validation', function (): void {
        // Refused outright while abbreviations are off. With them on it is answered by the
        // abbreviation strategy — the one that knows `CST` names sixty-two zones and says so in the
        // confidence and the alternatives — rather than waved through as if it were an identifier.
        $resolution = $this->timezones->allowingAbbreviations()->explain(TimezoneAbbreviation::CST);

        expect($this->timezones->tryOf(TimezoneAbbreviation::CST))->toBeNull()
            ->and($resolution?->via)->toBe('abbreviation')
            ->and($resolution?->confidence)->toBeLessThan(0.5)
            ->and($resolution?->alternatives)->toHaveCount(62);
    });

    it('reads a Timezone object exactly, without rewriting it', function (): void {
        $legacy = $this->timezones->preservingAliases()->of('Asia/Calcutta');

        expect($this->timezones->of($legacy)->identifier)->toBe('Asia/Calcutta');
    });
});
