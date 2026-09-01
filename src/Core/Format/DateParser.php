<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Format;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use NoDiscard;
use Simtabi\Laranail\Chrono\Core\Enums\AmbiguityPolicy;
use Simtabi\Laranail\Chrono\Core\Enums\GapPolicy;
use Simtabi\Laranail\Chrono\Core\Enums\NamedFormat;
use Simtabi\Laranail\Chrono\Core\Exception\UnparsableDateTime;
use Simtabi\Laranail\Chrono\Core\Timezone\Support\LocalTimeResolver;

/**
 * Parses a string into an instant, closing two traps in PHP's own parsing.
 *
 * **The `$timezone` argument is silently ignored when the string carries an offset.** Verified:
 * `DateTimeImmutable::createFromFormat('Y-m-d H:i:sP', '2026-06-01 12:00:00+05:00', $newYork)`
 * returns `+05:00`, not New York. The argument is documented as a *default* for when the string has
 * no zone of its own, and nothing warns you when it is discarded. `parse()` reports the conflict in
 * strict mode instead.
 *
 * **A wall-clock string may name no instant, or two.** Anything without an offset is a local
 * reading, so it goes through the same gap and ambiguity policies as `Timezone::at()` rather than
 * inheriting PHP's silent resolution.
 */
final readonly class DateParser
{
    public function __construct(
        private LocalTimeResolver $localTimes = new LocalTimeResolver,
        private bool $strict = true,
    ) {}

    /**
     * @throws UnparsableDateTime when the value cannot be read, or conflicts with the zone in strict mode
     */
    #[NoDiscard]
    public function parse(
        string $value,
        ?DateTimeZone $zone = null,
        GapPolicy $gap = GapPolicy::Forward,
        AmbiguityPolicy $ambiguity = AmbiguityPolicy::Earlier,
    ): DateTimeImmutable {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw UnparsableDateTime::for($value);
        }

        $carriesOffset = $this->carriesOffset($trimmed);

        if ($carriesOffset) {
            $instant = $this->parseAbsolute($trimmed);

            if ($zone instanceof DateTimeZone && $this->strict) {
                $offset = $instant->format('P');
                $zoneOffset = new DateTimeImmutable('@'.$instant->getTimestamp())
                    ->setTimezone($zone)
                    ->format('P');

                if ($offset !== $zoneOffset) {
                    throw UnparsableDateTime::conflictingOffset($trimmed, $zone->getName(), $offset);
                }
            }

            return $zone instanceof DateTimeZone ? $instant->setTimezone($zone) : $instant;
        }

        // No offset in the string: it is a wall-clock reading in the requested zone, and may be
        // skipped or repeated by a daylight-saving change.
        $target = $zone ?? new DateTimeZone(date_default_timezone_get());
        $normalised = $this->normalise($trimmed);

        return $this->localTimes->resolve($normalised, $target, $gap, $ambiguity);
    }

    #[NoDiscard]
    public function tryParse(string $value, ?DateTimeZone $zone = null): ?DateTimeImmutable
    {
        try {
            return $this->parse($value, $zone);
        } catch (UnparsableDateTime) {
            return null;
        }
    }

    /** Parse against an explicit pattern or named format. */
    #[NoDiscard]
    public function parseFormat(
        string $value,
        NamedFormat|string $format,
        ?DateTimeZone $zone = null,
    ): DateTimeImmutable {
        $pattern = $format instanceof NamedFormat
            ? ($format->pattern() ?? throw UnparsableDateTime::for($value, $format->value, ['That format is locale-dependent and cannot be parsed against a fixed pattern.']))
            : $format;

        // The `!` resets every unspecified field to the epoch, so a date-only pattern does not
        // silently inherit the current time of day.
        $parsed = DateTimeImmutable::createFromFormat('!'.$pattern, $value, $zone);

        if ($parsed === false) {
            $errors = DateTimeImmutable::getLastErrors();

            throw UnparsableDateTime::for($value, $pattern, [
                ...($errors['errors'] ?? []),
                ...($errors['warnings'] ?? []),
            ]);
        }

        return $parsed;
    }

    #[NoDiscard]
    public function lenient(): self
    {
        return clone ($this, ['strict' => false]);
    }

    /** Does the string carry its own UTC offset or zone name? */
    private function carriesOffset(string $value): bool
    {
        return preg_match('/(?:[+-]\d{2}:?\d{2}|\bZ$|\bUTC\b|\bGMT\b|\b[A-Z]{3,5}\b\s*$)/', $value) === 1
            || str_starts_with($value, '@');
    }

    private function parseAbsolute(string $value): DateTimeImmutable
    {
        try {
            return new DateTimeImmutable($value);
        } catch (Exception $e) {
            throw UnparsableDateTime::for($value, null, [$e->getMessage()]);
        }
    }

    /** Reduce a free-form local string to the `Y-m-d H:i:s` the wall-clock resolver expects. */
    private function normalise(string $value): string
    {
        try {
            // Parsed in UTC purely to read its wall-clock fields; the zone is applied afterwards by
            // the resolver, which is what makes gap and ambiguity handling possible.
            return new DateTimeImmutable($value, new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            throw UnparsableDateTime::for($value, null, [$e->getMessage()]);
        }
    }
}
