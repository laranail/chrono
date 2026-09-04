<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Enums;

/** A month of the Gregorian year, numbered as everyone expects: January is 1. */
enum Month: int
{
    case January = 1;
    case February = 2;
    case March = 3;
    case April = 4;
    case May = 5;
    case June = 6;
    case July = 7;
    case August = 8;
    case September = 9;
    case October = 10;
    case November = 11;
    case December = 12;

    /** Divisible by 4, except centuries, except every fourth century. */
    public static function isLeapYear(int $year): bool
    {
        return ($year % 4 === 0 && $year % 100 !== 0) || $year % 400 === 0;
    }

    /**
     * How many days this month has in a given year.
     *
     * The year is required rather than optional because February has no answer without it, and a
     * default would make the one case that matters the one most easily got wrong.
     */
    public function length(int $year): int
    {
        return match ($this) {
            self::February                                           => self::isLeapYear($year) ? 29 : 28,
            self::April, self::June, self::September, self::November => 30,
            default                                                  => 31,
        };
    }

    public function quarter(): int
    {
        return intdiv($this->value - 1, 3) + 1;
    }

    public function next(): self
    {
        return self::from($this->value === 12 ? 1 : $this->value + 1);
    }

    public function previous(): self
    {
        return self::from($this->value === 1 ? 12 : $this->value - 1);
    }
}
