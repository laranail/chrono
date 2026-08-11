<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Enums;

/**
 * The granularities a humanised difference is expressed in.
 *
 * The thresholds are the conventional ones: 45 seconds becomes "a minute", 45 minutes becomes "an
 * hour". Months and years use average lengths, because "3 months ago" is a description rather than
 * an arithmetic claim — anyone needing exact calendar arithmetic wants a `Period`, not a phrase.
 */
enum TimeUnit: string
{
    case Second = 'second';
    case Minute = 'minute';
    case Hour = 'hour';
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
    case Year = 'year';

    /** Average length in seconds. Months and years are approximations by design. */
    public function seconds(): int
    {
        return match ($this) {
            self::Second => 1,
            self::Minute => 60,
            self::Hour => 3600,
            self::Day => 86400,
            self::Week => 604800,
            self::Month => 2629746,   // 365.2425 / 12 days
            self::Year => 31556952,   // 365.2425 days
        };
    }

    /** Above this many seconds, promote to the next unit. */
    public function threshold(): int
    {
        return match ($this) {
            self::Second => 45,
            self::Minute => 2700,      // 45 minutes
            self::Hour => 79200,       // 22 hours
            self::Day => 518400,       // 6 days
            self::Week => 2246400,     // 26 days
            self::Month => 27561600,   // 319 days
            self::Year => PHP_INT_MAX,
        };
    }

    /** @return list<self> smallest to largest */
    public static function ascending(): array
    {
        return [self::Second, self::Minute, self::Hour, self::Day, self::Week, self::Month, self::Year];
    }

    /** The largest unit that describes a span without exaggerating it. */
    public static function forSeconds(int $seconds): self
    {
        $absolute = abs($seconds);

        foreach (self::ascending() as $unit) {
            if ($absolute < $unit->threshold()) {
                return $unit;
            }
        }

        return self::Year;
    }
}
