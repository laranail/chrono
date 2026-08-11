<?php

declare(strict_types=1);

use Simtabi\Laranail\Chrono\Core\Config\DisplayOptions;
use Simtabi\Laranail\Chrono\Core\Enums\OffsetFormat;
use Simtabi\Laranail\Chrono\Core\Enums\Timezone as TimezoneEnum;
use Simtabi\Laranail\Chrono\Core\Exception\TimezoneNotFound;
use Simtabi\Laranail\Chrono\Core\Timezone\Timezones;
use Simtabi\Laranail\Chrono\Core\Timezone\Value\Timezone;

/**
 * The three global helpers are autoloaded through `composer.json`'s `files` entry, listed in the
 * documentation as supported surface, and until now had no test of any kind — so nothing would have
 * noticed if `composer dump-autoload` stopped registering them, or if one drifted from the service
 * it delegates to.
 *
 * They resolve out of the container, so they belong in Feature rather than Unit.
 */
it('registers every helper the docs promise', function (string $helper): void {
    expect(function_exists($helper))->toBeTrue();
})->with(['timezones', 'tz_offset', 'in_timezone']);

describe('timezones()', function (): void {
    it('hands back the same service the container holds', function (): void {
        // Not merely *a* service: the configured one. A helper that built its own would quietly
        // opt out of the catalogue and daylight-saving policy everything else obeys.
        expect(timezones())->toBe(app(Timezones::class));
    });

    it('resolves a zone when given one', function (): void {
        expect(timezones('Africa/Nairobi'))->toBeInstanceOf(Timezone::class)
            ->and(timezones('Africa/Nairobi')->identifier)->toBe('Africa/Nairobi');
    });

    it('takes the same typed input the service does', function (): void {
        expect(timezones(TimezoneEnum::AsiaTokyo)->identifier)->toBe('Asia/Tokyo')
            // And canonicalises, so a deprecated spelling cannot reach a database through it.
            ->and(timezones('Asia/Calcutta')->identifier)->toBe('Asia/Kolkata');
    });

    it('throws on something that names no zone', function (): void {
        expect(fn (): Timezones|\Simtabi\Laranail\Chrono\Core\Timezone\Value\Timezone => timezones('not a zone'))
            ->toThrow(TimezoneNotFound::class);
    });
});

describe('tz_offset()', function (): void {
    it('formats the offset in the configured shape', function (): void {
        // `utc` is the default display format, and the helper must honour it rather than pick one.
        expect(tz_offset('Africa/Nairobi'))->toBe('UTC +03:00');
    });

    /** The bug this test was written to find: it used to ignore the setting entirely. */
    it('follows the configured shape when the application changes it', function (): void {
        config()->set('laranail.chrono.display.offset_format', 'colon');
        app()->forgetInstance(DisplayOptions::class);

        expect(tz_offset('Africa/Nairobi'))->toBe('+03:00');
    });

    it('still lets one call site ask for something else', function (): void {
        expect(tz_offset('Africa/Nairobi', null, OffsetFormat::Short))
            ->toBe('+3');
    });

    it('answers for an instant, not just for now', function (): void {
        $january = new DateTimeImmutable('2026-01-15T12:00:00Z');
        $july = new DateTimeImmutable('2026-07-15T12:00:00Z');

        // New York observes daylight saving; Nairobi does not, and never has.
        expect(tz_offset('America/New_York', $january))->toBe('UTC -05:00')
            ->and(tz_offset('America/New_York', $july))->toBe('UTC -04:00')
            ->and(tz_offset('Africa/Nairobi', $january))->toBe(tz_offset('Africa/Nairobi', $july));
    });
});

describe('in_timezone()', function (): void {
    it('changes how an instant reads without changing the instant', function (): void {
        $instant = new DateTimeImmutable('2026-06-15T12:00:00Z');
        $tokyo = in_timezone($instant, 'Asia/Tokyo');

        expect($tokyo->format('H:i'))->toBe('21:00')
            ->and($tokyo->getTimestamp())->toBe($instant->getTimestamp());
    });

    it('returns an immutable value whatever it was handed', function (): void {
        // A mutable DateTime in must not come back as something the caller can mutate underneath us.
        $mutable = new DateTime('2026-06-15T12:00:00Z');

        expect(in_timezone($mutable, 'UTC'))->toBeInstanceOf(DateTimeImmutable::class);
    });
});
