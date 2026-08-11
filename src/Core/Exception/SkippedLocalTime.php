<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Exception;

use Simtabi\Laranail\Chrono\Core\Timezone\Value\Transition;

/**
 * The wall-clock reading never existed: the clock jumped over it when daylight saving began.
 *
 * PHP resolves this silently — `new DateTimeImmutable('2026-03-08 02:30', new DateTimeZone('America/New_York'))`
 * becomes 03:30 EDT with no signal. This exception is what `GapPolicy::Throw` raises instead.
 */
final class SkippedLocalTime extends ChronoExceptionBase
{
    public static function for(string $localTime, string $identifier, ?Transition $transition): self
    {
        $exception = new self(sprintf(
            'The local time "%s" does not exist in %s; the clock skipped it when daylight saving began.',
            $localTime,
            $identifier,
        ));

        $exception->context = [
            'local_time' => $localTime,
            'identifier' => $identifier,
            'gap_seconds' => $transition?->delta()->seconds,
            'transition_at' => $transition?->at->format('c'),
        ];

        return $exception;
    }
}
