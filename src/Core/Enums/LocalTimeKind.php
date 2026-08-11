<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Enums;

/** How a wall-clock reading relates to a zone's daylight-saving transitions. */
enum LocalTimeKind: string
{
    /** Exactly one instant matches. The overwhelmingly common case. */
    case Valid = 'valid';

    /** No instant matches — the clock skipped this reading. */
    case Gap = 'gap';

    /** More than one instant matches — the clock repeated this reading. */
    case Ambiguous = 'ambiguous';
}
