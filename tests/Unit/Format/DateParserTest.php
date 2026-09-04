<?php

declare(strict_types=1);

use Simtabi\Laranail\Chrono\Core\Enums\GapPolicy;
use Simtabi\Laranail\Chrono\Core\Enums\NamedFormat;
use Simtabi\Laranail\Chrono\Core\Format\DateParser;
use Simtabi\Laranail\Chrono\Core\Exception\SkippedLocalTime;
use Simtabi\Laranail\Chrono\Core\Exception\UnparsableDateTime;

beforeEach(function (): void {
    $this->parser = new DateParser;
    $this->newYork = zone('America/New_York');
});

/**
 * `createFromFormat()` documents its `$timezone` argument as a default for strings that carry no
 * zone — and then silently discards it when they do. Nothing warns you. This is the behaviour being
 * guarded against, asserted here so the guard cannot rot.
 */
it('demonstrates the trap it exists to close', function (): void {
    $native = DateTimeImmutable::createFromFormat('Y-m-d H:i:sP', '2026-06-01 12:00:00+05:00', $this->newYork);

    expect($native->format('P'))->toBe('+05:00');
});

it('refuses a value whose offset contradicts the requested zone', function (): void {
    try {
        (void) $this->parser->parse('2026-06-01 12:00:00+05:00', $this->newYork);
        $this->fail('Expected UnparsableDateTime.');
    } catch (UnparsableDateTime $e) {
        expect($e->context()['offset'])->toBe('+05:00')
            ->and($e->context()['zone'])->toBe('America/New_York');
    }
});

it('converts instead of refusing when lenient, preserving the instant', function (): void {
    $lenient = $this->parser->lenient()->parse('2026-06-01 12:00:00+05:00', $this->newYork);
    $native = DateTimeImmutable::createFromFormat('Y-m-d H:i:sP', '2026-06-01 12:00:00+05:00', $this->newYork);

    expect($lenient->format('P'))->toBe('-04:00')
        ->and($lenient->getTimestamp())->toBe($native->getTimestamp());
});

it('routes a bare wall-clock reading through the daylight-saving policies', function (): void {
    expect($this->parser->parse('2026-03-08 02:30', $this->newYork, GapPolicy::Forward)->format('H:i T'))
        ->toBe('03:30 EDT')
        ->and($this->parser->parse('2026-06-15 12:00', $this->newYork)->format('H:i T'))
        ->toBe('12:00 EDT');
});

it('refuses a skipped local time when asked', function (): void {
    (void) $this->parser->parse('2026-03-08 02:30', $this->newYork, GapPolicy::Throw);
})->throws(SkippedLocalTime::class);

/** The `!` prefix resets unspecified fields, so a date-only pattern does not inherit the clock. */
it('zeroes the time when parsing a date-only format', function (): void {
    expect($this->parser->parseFormat('2026-06-15', NamedFormat::SqlDate, zone('UTC'))->format('H:i:s'))
        ->toBe('00:00:00');
});

it('returns null rather than throwing when asked politely', function (): void {
    expect($this->parser->tryParse('not a date'))->toBeNull();
});

it('refuses to parse a locale-dependent format against a fixed pattern', function (): void {
    (void) $this->parser->parseFormat('Jun 15, 2026', NamedFormat::MediumDate);
})->throws(UnparsableDateTime::class);
