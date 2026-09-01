<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Enums;

use DateTimeInterface;
use Simtabi\Laranail\Chrono\Core\Timezone\Value\Timezone;

/**
 * A sortable, groupable, pluckable property of a timezone.
 *
 * One enum rather than separate `SortBy` and `GroupBy` types, because ordering by offset and
 * grouping by offset need the same accessor — splitting them would mean two `match` blocks that
 * have to be kept in step.
 */
enum TimezoneField: string
{
    case Identifier = 'identifier';
    case City = 'city';
    case Country = 'country';
    case Region = 'region';
    case Offset = 'offset';
    case Abbreviation = 'abbreviation';

    public function valueFor(Timezone $timezone, ?DateTimeInterface $at = null): string|int
    {
        return match ($this) {
            self::Identifier => $timezone->identifier,
            self::City => $timezone->city(),
            self::Country => $timezone->countryCode() ?? '',
            self::Region => $timezone->region()->value ?? '',
            self::Offset => $timezone->offset($at)->seconds,
            self::Abbreviation => $timezone->abbreviation($at),
        };
    }

    /** @return callable(Timezone, Timezone): int */
    public function comparator(bool $descending = false, ?DateTimeInterface $at = null): callable
    {
        return function (Timezone $a, Timezone $b) use ($descending, $at): int {
            $left = $this->valueFor($a, $at);
            $right = $this->valueFor($b, $at);

            // Ties break on the identifier so ordering is stable across runs — without it, two
            // zones sharing an offset can swap places between calls.
            $result = $left <=> $right;

            if ($result === 0) {
                $result = $a->identifier <=> $b->identifier;
            }

            return $descending ? -$result : $result;
        };
    }
}
