<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Enums\Concerns;

use BackedEnum;
use DateTimeZone;

/**
 * Behaviour for the generated abbreviation enum.
 *
 * The important method is `isAmbiguous()`. Abbreviations look like identifiers and are not: 96 of
 * the 144 PHP knows map to more than one zone, `CST` to 62 of them. Anything that stores an
 * abbreviation as if it were a timezone has already lost information.
 *
 * @mixin BackedEnum
 */
trait DescribesAbbreviation
{
    /** @return list<string> every identifier that uses this abbreviation */
    public function identifiers(): array
    {
        $entries = DateTimeZone::listAbbreviations()[strtolower($this->value)] ?? [];

        $identifiers = [];

        foreach ($entries as $entry) {
            $identifier = $entry['timezone_id'] ?? null;

            if (is_string($identifier) && $identifier !== '' && ! in_array($identifier, $identifiers, true)) {
                $identifiers[] = $identifier;
            }
        }

        sort($identifiers);

        return $identifiers;
    }

    public function isAmbiguous(): bool
    {
        return count($this->identifiers()) > 1;
    }

    /** The UTC offset this abbreviation implies, or null when its uses disagree. */
    public function offsetSeconds(): ?int
    {
        $entries = DateTimeZone::listAbbreviations()[strtolower($this->value)] ?? [];

        $offsets = array_unique(array_map(
            static fn (array $entry): int => $entry['offset'],
            $entries,
        ));

        return count($offsets) === 1 ? reset($offsets) : null;
    }

    public function isDaylightSaving(): bool
    {
        $entries = DateTimeZone::listAbbreviations()[strtolower($this->value)] ?? [];

        foreach ($entries as $entry) {
            if ($entry['dst']) {
                return true;
            }
        }

        return false;
    }
}
