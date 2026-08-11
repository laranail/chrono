<?php

declare(strict_types=1);

use Simtabi\Laranail\Chrono\Core\Enums\NamedFormat;
use Simtabi\Laranail\Chrono\Core\Format\DateFormatter;

beforeEach(function (): void {
    $this->formatter = new DateFormatter;
    $this->when = new DateTimeImmutable('2026-06-15 14:30:00', zone('UTC'));
});

/**
 * Machine formats must be byte-identical everywhere. An ISO 8601 timestamp rendered with Japanese
 * era names is how a value ends up in a column nothing can read back.
 */
it('never localises a machine format', function (NamedFormat $format, string $expected): void {
    expect($this->formatter->format($this->when, $format))->toBe($expected)
        ->and($this->formatter->format($this->when, $format, locale: 'ja_JP'))
        ->toBe($this->formatter->format($this->when, $format, locale: 'en_US'));
})->with([
    [NamedFormat::Iso8601, '2026-06-15T14:30:00+00:00'],
    [NamedFormat::Sql, '2026-06-15 14:30:00'],
    [NamedFormat::SqlDate, '2026-06-15'],
    [NamedFormat::SqlTime, '14:30:00'],
]);

/**
 * Human formats resolve through an ICU skeleton, so each locale gets its own field order and
 * separators rather than American ordering with translated month names.
 */
it('gives each locale its own date ordering', function (): void {
    $rendered = array_map(
        fn (string $locale): string => $this->formatter->format($this->when, NamedFormat::MediumDate, locale: $locale),
        ['en_US', 'de_DE', 'ja_JP'],
    );

    expect($rendered)->toHaveCount(3)
        ->and(count(array_unique($rendered)))->toBe(3);
});

it('applies the zone before formatting', function (): void {
    expect($this->formatter->format($this->when, NamedFormat::ShortTime, locale: 'en_GB'))->toBe('14:30')
        ->and($this->formatter->format($this->when, NamedFormat::ShortTime, zone('Africa/Nairobi'), 'en_GB'))->toBe('17:30');
});

it('treats an unrecognised string as a raw PHP pattern', function (): void {
    expect($this->formatter->format($this->when, 'Y/m/d'))->toBe('2026/06/15');
});

it('renders every named format at once', function (): void {
    $all = $this->formatter->all($this->when);

    expect($all)->toHaveKey(NamedFormat::Iso8601->value)
        ->and($all)->toHaveKey(NamedFormat::MediumDate->value)
        ->and($all[NamedFormat::Iso8601->value])->toBe('2026-06-15T14:30:00+00:00');
});
