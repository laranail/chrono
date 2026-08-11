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
        $entries = $this->entries();

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
        $entries = $this->entries();

        $offsets = array_unique(array_map(
            static fn (array $entry): int => $entry['offset'],
            $entries,
        ));

        return count($offsets) === 1 ? reset($offsets) : null;
    }

    public function isDaylightSaving(): bool
    {
        foreach ($this->entries() as $entry) {
            if ($entry['dst']) {
                return true;
            }
        }

        return false;
    }

    /**
     * This abbreviation's rows, from a table built once per process.
     *
     * `listAbbreviations()` rebuilds a 144-key structure on every call — 0.41 ms measured — and the
     * three methods above each used to ask for it separately, so describing one abbreviation cost
     * three full rebuilds. The table comes from the tz database compiled into PHP and cannot change
     * while the process runs.
     *
     * A function static rather than a class property: an enum may not declare properties at all,
     * and a trait that tries fails at class-load with a fatal error rather than at analysis.
     *
     * @return list<array{dst: bool, offset: int, timezone_id: string|null}>
     */
    private function entries(): array
    {
        /** @var array<string, list<array{dst: bool, offset: int, timezone_id: string|null}>>|null $table */
        static $table = null;

        $table ??= DateTimeZone::listAbbreviations();

        return $table[strtolower($this->value)] ?? [];
    }
}
