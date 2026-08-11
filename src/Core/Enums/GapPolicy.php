<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Enums;

/**
 * What to do with a wall-clock reading that never existed, because the clock jumped forward.
 *
 * `2026-03-08 02:30` in `America/New_York` is such a reading. PHP resolves it to 03:30 EDT with no
 * signal at all, which is `Forward` — chosen as the default so migrating code behaves identically
 * until it opts into something stricter.
 */
enum GapPolicy: string
{
    /** Shift forward by the gap: 02:30 becomes 03:30. Matches PHP's own silent behaviour. */
    case Forward = 'forward';

    /** Shift backward by the gap: 02:30 becomes 01:30. Preserves duration when pairing times. */
    case Backward = 'backward';

    /** Refuse. The right choice for bookings, payroll and anything a human will be billed for. */
    case Throw = 'throw';
}
