<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Timezone\Support;

use DateTimeImmutable;
use DateTimeZone;
use Simtabi\Laranail\Chrono\Core\Contracts\Clock;

/**
 * The real clock.
 *
 * The only place in `src/` allowed to ask the operating system what time it is — an architecture
 * test asserts nothing else calls `time()`, `date()` or constructs an unqualified `DateTimeImmutable`.
 * Everything else takes a `ClockInterface`, which is what makes a daylight-saving assertion
 * reproducible in five years' time rather than only during the month it was written.
 */
final readonly class SystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
