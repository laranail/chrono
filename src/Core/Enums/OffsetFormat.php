<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Enums;

/**
 * How a UTC offset renders. Formatting lives on the enum rather than in a separate formatter class
 * because the mapping is total, closed, and has no dependencies — a class would be indirection for
 * its own sake.
 *
 * `Utc` reproduces the `UTC +03:00` shape that `simtabi/pheg`'s `Time::formatDisplayOffset()`
 * emitted, so a migrating caller gets byte-identical output.
 *
 * Historical offsets are not whole minutes — `Asia/Manila`'s LMT is −15:56:00 and several LMT
 * offsets carry seconds — so every format appends `:ss` when the seconds part is non-zero rather
 * than silently truncating it.
 */
enum OffsetFormat: string
{
    /** `+03:00` · `-05:30` · `+00:00` */
    case Colon = 'colon';

    /** `+0300` · `-0530` · `+0000` */
    case Compact = 'compact';

    /** `+3` · `-5:30` · `+0` — the shortest unambiguous form */
    case Short = 'short';

    /** `GMT+03:00` · `GMT-05:30` · `GMT` */
    case Gmt = 'gmt';

    /** `UTC +03:00` · `UTC -05:30` · `UTC` */
    case Utc = 'utc';

    /** `+03:00` · `-05:30` · `Z` — ISO 8601, where zero is `Z` */
    case Iso8601 = 'iso8601';

    /** `10800` · `-19800` · `0` */
    case Seconds = 'seconds';

    public function format(int $seconds): string
    {
        if ($this === self::Seconds) {
            return (string) $seconds;
        }

        $sign = $seconds < 0 ? '-' : '+';
        $absolute = abs($seconds);

        $hours = intdiv($absolute, 3600);
        $minutes = intdiv($absolute % 3600, 60);
        $remainder = $absolute % 60;

        return match ($this) {
            self::Colon   => $sign . self::pad($hours, $minutes, $remainder, ':'),
            self::Compact => $sign . self::pad($hours, $minutes, $remainder, ''),
            self::Short   => self::short($sign, $hours, $minutes, $remainder),
            self::Gmt     => $seconds === 0 ? 'GMT' : 'GMT' . $sign . self::pad($hours, $minutes, $remainder, ':'),
            self::Utc     => $seconds === 0 ? 'UTC' : 'UTC ' . $sign . self::pad($hours, $minutes, $remainder, ':'),
            self::Iso8601 => $seconds === 0 ? 'Z' : $sign . self::pad($hours, $minutes, $remainder, ':'),
        };
    }

    private static function pad(int $hours, int $minutes, int $seconds, string $separator): string
    {
        $formatted = sprintf('%02d%s%02d', $hours, $separator, $minutes);

        return $seconds === 0 ? $formatted : $formatted . $separator . sprintf('%02d', $seconds);
    }

    private static function short(string $sign, int $hours, int $minutes, int $seconds): string
    {
        if ($minutes === 0 && $seconds === 0) {
            return $sign . $hours;
        }

        $formatted = $sign . $hours . ':' . sprintf('%02d', $minutes);

        return $seconds === 0 ? $formatted : $formatted . ':' . sprintf('%02d', $seconds);
    }
}
