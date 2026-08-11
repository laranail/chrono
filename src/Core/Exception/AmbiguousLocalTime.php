<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Exception;

use DateTimeImmutable;

/**
 * The wall-clock reading happened twice: the clock repeated it when daylight saving ended.
 *
 * PHP picks one silently, and *which* one it picks varies by zone — `Europe/London 2025-10-26 01:30`
 * resolves to the later instant while `America/New_York 2025-11-02 01:30` resolves to the earlier.
 * This exception is what `AmbiguityPolicy::Throw` raises instead of inheriting that inconsistency.
 */
final class AmbiguousLocalTime extends ChronoExceptionBase
{
    /** @param list<DateTimeImmutable> $candidates */
    public static function for(string $localTime, string $identifier, array $candidates): self
    {
        $exception = new self(sprintf(
            'The local time "%s" is ambiguous in %s; it occurred %d times when daylight saving ended.',
            $localTime,
            $identifier,
            count($candidates),
        ));

        $exception->context = [
            'local_time' => $localTime,
            'identifier' => $identifier,
            'candidates' => array_map(static fn (DateTimeImmutable $c): string => $c->format('c'), $candidates),
        ];

        return $exception;
    }
}
