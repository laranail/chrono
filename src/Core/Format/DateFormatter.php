<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Format;

use NoDiscard;
use DateTimeZone;
use DateTimeImmutable;
use DateTimeInterface;
use IntlDateFormatter;
use Simtabi\Laranail\Chrono\Core\Enums\NamedFormat;

/**
 * Formats an instant, in a zone, for a locale.
 *
 * The whole point is that a machine format and a human format are not interchangeable. Machine
 * formats go through `DateTimeInterface::format()` with a fixed pattern, because an ISO 8601
 * timestamp must be byte-identical everywhere. Human formats go through ICU with a *skeleton*, so
 * `MediumDate` renders as `Jun 15, 2026` in en_US, `15. Juni 2026` in de_DE and `2026年6月15日` in
 * ja_JP without anyone writing three patterns.
 *
 * Reaching for `IntlDateFormatter` directly is a trap this class exists to close — see
 * `CalendarFormatter` for the calendar half of it.
 */
final readonly class DateFormatter
{
    public function __construct(
        private string $defaultLocale = 'en',
        private DateFormatterCache $patterns = new DateFormatterCache,
    ) {}

    /** Format by name — the common case. */
    #[NoDiscard]
    public function format(
        DateTimeInterface $instant,
        NamedFormat|string $format = NamedFormat::MediumDateTime,
        ?DateTimeZone $zone = null,
        ?string $locale = null,
    ): string {
        if (is_string($format)) {
            $named = NamedFormat::tryFrom($format);

            // An unrecognised string is treated as a raw PHP pattern, so callers keep an escape
            // hatch without having to reach past this class.
            return $named === null
                ? $this->raw($instant, $format, $zone)
                : $this->format($instant, $named, $zone, $locale);
        }

        $pattern = $format->pattern();

        if ($pattern !== null) {
            return $this->raw($instant, $pattern, $zone);
        }

        return $this->skeleton($instant, (string) $format->skeleton(), $zone, $locale);
    }

    /** Format with an explicit PHP pattern. Never localised. */
    #[NoDiscard]
    public function raw(DateTimeInterface $instant, string $pattern, ?DateTimeZone $zone = null): string
    {
        return $this->inZone($instant, $zone)->format($pattern);
    }

    /**
     * Format with an ICU skeleton, letting the locale decide field order and separators.
     *
     * The skeleton is resolved through `IntlDatePatternGenerator`, which is why this produces
     * `15. Juni 2026` for German rather than the American ordering with German month names.
     */
    #[NoDiscard]
    public function skeleton(
        DateTimeInterface $instant,
        string $skeleton,
        ?DateTimeZone $zone = null,
        ?string $locale = null,
    ): string {
        $resolvedLocale = $locale ?? $this->defaultLocale;
        $resolvedZone = $zone ?? $instant->getTimezone();

        $pattern = $this->patterns->patternFor($resolvedLocale, $skeleton);

        $formatter = new IntlDateFormatter(
            $resolvedLocale,
            IntlDateFormatter::FULL,
            IntlDateFormatter::FULL,
            $this->icuZoneName($resolvedZone, $instant),
            null,
            $pattern,
        );

        $formatted = $formatter->format($instant);

        // ICU returns false rather than throwing; fall back rather than emit an empty string.
        return $formatted === false
            ? $this->raw($instant, 'Y-m-d H:i', $zone)
            : $formatted;
    }

    /**
     * Every named format at once — useful for a debug panel or an API that offers choices.
     *
     * @return array<string, string>
     */
    #[NoDiscard]
    public function all(DateTimeInterface $instant, ?DateTimeZone $zone = null, ?string $locale = null): array
    {
        $formatted = [];

        foreach (NamedFormat::cases() as $case) {
            $formatted[$case->value] = $this->format($instant, $case, $zone, $locale);
        }

        return $formatted;
    }

    #[NoDiscard]
    public function withLocale(string $locale): self
    {
        return clone ($this, ['defaultLocale' => $locale]);
    }

    /**
     * A zone name ICU will accept, which is not the same set PHP will produce.
     *
     * `new DateTimeImmutable('2026-06-15T12:00:00Z')` yields a zone named `Z`, and an ISO string
     * with an offset yields one named `+03:00`. ICU knows neither, and rejects both by throwing
     * from the constructor — so formatting any instant parsed from a JSON payload, an API response
     * or a round-tripped `format('c')` used to be a fatal error rather than a rendering.
     *
     * Only region zones survive unchanged. The other two PHP zone types are rendered as a fixed
     * `GMT±HH:MM`, which ICU understands and which is honest: an offset zone carries no region, so
     * there is no locale-specific name to be had.
     */
    private function icuZoneName(DateTimeZone $zone, DateTimeInterface $instant): string
    {
        $name = $zone->getName();

        // A region zone — the only kind ICU has data for.
        if (str_contains($name, '/') || $name === 'UTC') {
            return $name;
        }

        $offset = $zone->getOffset(DateTimeImmutable::createFromInterface($instant));

        if ($offset === 0) {
            return 'UTC';
        }

        return sprintf(
            'GMT%s%02d:%02d',
            $offset < 0 ? '-' : '+',
            intdiv(abs($offset), 3600),
            intdiv(abs($offset) % 3600, 60),
        );
    }

    private function inZone(DateTimeInterface $instant, ?DateTimeZone $zone): DateTimeInterface
    {
        if (! $zone instanceof DateTimeZone) {
            return $instant;
        }

        return DateTimeImmutable::createFromInterface($instant)->setTimezone($zone);
    }
}
