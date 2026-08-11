<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Enums;

/**
 * What to do with a wall-clock reading that happened twice, because the clock fell back.
 *
 * PHP picks one silently, and which one it picks is *not consistent between zones*: verified,
 * `Europe/London 2025-10-26 01:30` resolves to the later instant while
 * `America/New_York 2025-11-02 01:30` resolves to the earlier one. Choosing explicitly is the whole
 * point. `Earlier` is the default because it matches ISO 8601, `java.time` and the JS Temporal
 * proposal, and because "the first time the clock read 01:30" is what a person means.
 */
enum AmbiguityPolicy: string
{
    /** The first occurrence — usually still on daylight saving. */
    case Earlier = 'earlier';

    /** The second occurrence — usually back on standard time. */
    case Later = 'later';

    /** Refuse, and let the caller ask the user which one they meant. */
    case Throw = 'throw';
}
