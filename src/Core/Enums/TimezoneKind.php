<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Enums;

/**
 * What sort of thing an identifier is.
 *
 * The distinction matters because three of these behave differently from the region zones everyone
 * pictures. `Fixed` and `Legacy` zones return `false` from `getTransitions()` and `getLocation()`;
 * `Link` zones are aliases whose canonical target cannot be derived from the database, only curated.
 */
enum TimezoneKind: string
{
    /** A canonical region zone: `Africa/Nairobi`. In `listIdentifiers()`. */
    case Canonical = 'canonical';

    /** A backward-compatible alias: `Asia/Calcutta`, `US/Eastern`. Only in `ALL_WITH_BC`. */
    case Link = 'link';

    /** A fixed-offset zone: `UTC`, `Etc/GMT+5`, `+03:00`. No rules, no location. */
    case Fixed = 'fixed';

    /** A rule-bearing abbreviation: `CET`, `EST5EDT`, `MET`. No canonical target, no location. */
    case Legacy = 'legacy';

    public function hasLocation(): bool
    {
        return $this === self::Canonical;
    }

    public function isSelectable(): bool
    {
        return $this === self::Canonical;
    }
}
