<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Enums;

/**
 * How presented zones are bucketed into `<optgroup>`s.
 *
 * `Continent` groups on the identifier's leading segment. That is what IANA calls a *region* and
 * what a picker calls a continent, and the two are not quite the same thing — `America` spans two
 * continents, and `Atlantic`, `Indian` and `Pacific` are oceans. It is nonetheless the grouping
 * users recognise, and it is the only one derivable from the identifier alone.
 */
enum GroupBy: string
{
    /** `Africa`, `America`, `Asia`, `Europe`, … — the identifier's leading segment. */
    case Continent = 'continent';

    /** ISO 3166-1 alpha-2. Zones with no country — `UTC`, `Etc/*` — fall into `catch_all`. */
    case Country = 'country';

    /** `UTC +03:00`, so everything on the same clock sits together regardless of geography. */
    case Offset = 'offset';

    /** No grouping: one flat list. */
    case None = 'none';
}
