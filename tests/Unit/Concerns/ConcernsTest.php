<?php

declare(strict_types=1);

use Simtabi\Laranail\Chrono\Core\Concerns\InteractsWithClock;
use Simtabi\Laranail\Chrono\Core\Concerns\RendersDateTimes;
use Simtabi\Laranail\Chrono\Core\Config\CatalogueOptions;
use Simtabi\Laranail\Chrono\Core\Config\DstPolicy;
use Simtabi\Laranail\Chrono\Core\Config\SelectOptions;
use Simtabi\Laranail\Chrono\Core\Enums\NamedFormat;
use Simtabi\Laranail\Chrono\Core\Enums\SelectShape;
use Simtabi\Laranail\Chrono\Core\Enums\Timezone as TimezoneEnum;
use Simtabi\Laranail\Chrono\Core\Exception\AmbiguousLocalTime;
use Simtabi\Laranail\Chrono\Core\Exception\SkippedLocalTime;
use Simtabi\Laranail\Chrono\Core\Support\ServiceResolver;
use Simtabi\Laranail\Chrono\Core\Testing\FrozenClock;
use Simtabi\Laranail\Chrono\Core\Timezone\Timezones;
use Simtabi\Laranail\Chrono\Tests\Fixtures\Core\ChronoConsumer;

/**
 * The traits' central claim is that one `use` line is correct in two worlds. These tests are the
 * half that has no container at all — a plain script, a console tool, a queue worker booted without
 * the framework. `ConfigWiringTest` covers the other half.
 */
beforeEach(function (): void {
    // Prove the no-framework path is genuinely taken, rather than quietly resolving through a
    // container some earlier feature test left installed.
    ServiceResolver::forget();

    $this->consumer = new ChronoConsumer;
});

it('needs no container, no constructor and no configuration', function (): void {
    expect(ServiceResolver::isBound())->toBeFalse()
        ->and($this->consumer->currentTimeIn('Africa/Nairobi'))->toMatch('/^\d{2}:\d{2}$/')
        ->and($this->consumer->offeredZones())->toHaveCount(419);
});

it('takes the same typed input the service does', function (): void {
    expect($this->consumer->currentTimeIn(TimezoneEnum::AfricaNairobi))
        ->toBe($this->consumer->currentTimeIn('Africa/Nairobi'));
});

describe('InteractsWithClock', function (): void {
    it('makes any class testable without touching its constructor', function (): void {
        $frozen = $this->consumer->withClock(new FrozenClock('2026-06-15T12:00:00Z'));

        expect($frozen->stamp()->format('c'))->toBe('2026-06-15T12:00:00+00:00')
            ->and($frozen->currentTimeIn('Africa/Nairobi'))->toBe('15:00');
    });

    /** The with-er clones, so handing an instance around cannot silently re-time somebody else. */
    it('leaves the original alone', function (): void {
        $frozen = $this->consumer->withClock(new FrozenClock('2000-01-01T00:00:00Z'));

        expect($frozen->stamp()->format('Y'))->toBe('2000')
            ->and($this->consumer->stamp()->format('Y'))->not->toBe('2000');
    });

    it('mutates in place when asked to, for a class assembled once', function (): void {
        $consumer = new ChronoConsumer;
        $consumer->setClock(new FrozenClock('2000-01-01T00:00:00Z'));

        expect($consumer->stamp()->format('Y'))->toBe('2000');
    });
});

describe('ResolvesLocalTimes', function (): void {
    it('defaults to the engine policy with nothing configured', function (): void {
        expect($this->consumer->bookingInstant('2026-03-08 02:30', 'America/New_York')->format('H:i'))
            ->toBe('03:30');
    });

    it('refuses to guess when the class says so', function (): void {
        $strict = $this->consumer->withDstPolicy(DstPolicy::strict());

        expect(fn () => $strict->bookingInstant('2026-03-08 02:30', 'America/New_York'))
            ->toThrow(SkippedLocalTime::class)
            ->and(fn () => $strict->bookingInstant('2026-11-01 01:30', 'America/New_York'))
            ->toThrow(AmbiguousLocalTime::class);
    });

    it('can ask instead of throwing, which is what a form wants', function (): void {
        expect($this->consumer->bookingIsReal('2026-03-08 02:30', 'America/New_York'))->toBeFalse()
            ->and($this->consumer->bookingIsReal('2026-03-08 04:30', 'America/New_York'))->toBeTrue();
    });
});

describe('ConvertsTimezones', function (): void {
    it('answers for many zones from one instant', function (): void {
        $instant = new DateTimeImmutable('2026-06-15T12:00:00Z');

        $rows = $this->consumer->acrossOffices($instant, ['Africa/Nairobi', 'Asia/Tokyo', 'Europe/London']);

        expect(array_keys($rows))->toEqualCanonicalizing(['Africa/Nairobi', 'Asia/Tokyo', 'Europe/London'])
            ->and($rows['Africa/Nairobi']->local->format('H:i'))->toBe('15:00')
            ->and($rows['Asia/Tokyo']->local->format('H:i'))->toBe('21:00')
            // June, so London is on BST rather than GMT.
            ->and($rows['Europe/London']->local->format('H:i'))->toBe('13:00');
    });

    it('shares one instant across every zone in the call', function (): void {
        $rows = $this->consumer->acrossOffices(
            new DateTimeImmutable('2026-06-15T12:00:00Z'),
            ['Africa/Nairobi', 'Asia/Tokyo'],
        );

        expect($rows['Africa/Nairobi']->instant->getTimestamp())
            ->toBe($rows['Asia/Tokyo']->instant->getTimestamp());
    });
});

describe('RendersDateTimes', function (): void {
    it('renders a date for a locale rather than in English forever', function (): void {
        $instant = new DateTimeImmutable('2026-06-15T12:00:00Z');

        expect($this->consumer->renderedFor($instant, 'en_US'))->toContain('2026')
            ->and($this->consumer->renderedFor($instant, 'ja_JP'))->toContain('年')
            ->and($this->consumer->renderedFor($instant, 'ja_JP'))
            ->not->toBe($this->consumer->renderedFor($instant, 'en_US'));
    });

    it('humanises against its own clock', function (): void {
        $frozen = $this->consumer->withClock(new FrozenClock('2026-06-15T12:00:00Z'));

        expect($frozen->agoFor(new DateTimeImmutable('2026-06-12T12:00:00Z'), 'en'))
            ->toContain('3');
    })->skip(
        fn (): bool => ! extension_loaded('intl'),
        'ext-intl carries the plural rules',
    );
});

describe('PresentsTimezones', function (): void {
    it('produces a picker array in the default shape', function (): void {
        $options = $this->consumer->pickerOptions();

        // `grouped` is the default shape, so the top level is continents.
        expect($options)->toHaveKey('Africa')
            ->and($options['Africa'])->toHaveKey('Africa/Nairobi');
    });

    it('honours an explicit shape', function (): void {
        $flat = (new ChronoConsumer)->withSelectOptions(
            new SelectOptions(SelectShape::Flat),
        );

        expect($flat->pickerOptions())->toHaveKey('Africa/Nairobi');
    });
});

describe('an explicit service always wins', function (): void {
    it('uses the one it is handed, not one it builds', function (): void {
        $restricted = (new Timezones)->withCatalogue(
            new CatalogueOptions(only: ['UTC', 'Africa/Nairobi']),
        );

        expect((new ChronoConsumer)->withTimezones($restricted)->offeredZones())
            ->toEqualCanonicalizing(['UTC', 'Africa/Nairobi']);
    });
});

describe('ServiceResolver', function (): void {
    it('returns null rather than throwing when a resolver fails', function (): void {
        ServiceResolver::using(function (string $service): ?object {
            throw new RuntimeException('container is mid-teardown');
        });

        // A date helper falling back to stock settings beats one that cannot construct.
        expect(ServiceResolver::resolve(Timezones::class))->toBeNull()
            ->and((new ChronoConsumer)->currentTimeIn('UTC'))->toMatch('/^\d{2}:\d{2}$/');

        ServiceResolver::forget();
    });

    it('ignores a resolver that answers with the wrong type', function (): void {
        ServiceResolver::using(static fn (string $service): object => new stdClass);

        expect(ServiceResolver::resolve(Timezones::class))->toBeNull();

        ServiceResolver::forget();
    });
});

/** A class may take one trait alone — the narrow dependency is the honest one. */
it('composes one trait at a time', function (): void {
    $stopwatch = new class
    {
        use InteractsWithClock;

        public function readingAt(): string
        {
            return $this->now()->format('Y-m-d');
        }
    };

    expect($stopwatch->withClock(new FrozenClock('2026-06-15T12:00:00Z'))->readingAt())
        ->toBe('2026-06-15');
});

it('formats with a named machine format that never localises', function (): void {
    $instant = new DateTimeImmutable('2026-06-15T12:00:00Z');
    $consumer = new class
    {
        use RendersDateTimes;

        public function iso(DateTimeInterface $instant): string
        {
            return $this->formatDate($instant, NamedFormat::Iso8601, 'ar_SA');
        }
    };

    // Arabic locale, Latin digits — a machine format is not a rendering decision.
    expect($consumer->iso($instant))->toStartWith('2026-06-15');
});
