<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Timezone\Support;

use Simtabi\Laranail\Chrono\Core\Exception\InvalidOffset;

/**
 * Every spelling of a UTC offset that a form, an API client or a config file might send.
 *
 * Accepts `+03:00`, `-0530`, `+3`, `+05:45:30`, `GMT+3`, `UTC-5`, `Z`, and a bare integer of
 * seconds. Rejects anything else rather than coercing it — `"3 o'clock"` is not an offset.
 */
final readonly class OffsetParser
{
    public static function parse(string $value): int
    {
        $seconds = self::tryParse($value);

        if ($seconds === null) {
            throw InvalidOffset::unparsable($value);
        }

        return $seconds;
    }

    public static function tryParse(string $value): ?int
    {
        $normalised = strtoupper(trim($value));

        if ($normalised === '') {
            return null;
        }

        if (in_array($normalised, ['Z', 'UTC', 'GMT'], true)) {
            return 0;
        }

        $normalised = preg_replace('/^(UTC|GMT)\s*/', '', $normalised) ?? $normalised;

        // Signed forms are offsets and are tried first. `-0530` means -05:30, not -530 seconds; the
        // bare-integer branch below would otherwise claim it, since it is also a valid integer.
        $signed = self::parseSigned($normalised);

        if ($signed !== null) {
            return $signed;
        }

        // Otherwise an integer is seconds — what `DateTimeZone::getOffset()` returns. A negative
        // value only reaches here when it could not be an offset: `-0530` was already claimed
        // above as -05:30, while `-19800` has no valid hour/minute reading and is 5.5 hours.
        if (preg_match('/^-?\d+$/', $normalised) === 1) {
            $seconds = (int) $normalised;

            return abs($seconds) <= 64800 ? $seconds : null;
        }

        return null;
    }

    private static function parseSigned(string $value): ?int
    {
        if (preg_match('/^([+-])(\d{1,2})(?::?(\d{2}))?(?::?(\d{2}))?$/', $value, $matches) !== 1) {
            return null;
        }

        $hours = (int) $matches[2];
        $minutes = (int) ($matches[3] ?? 0);
        $seconds = (int) ($matches[4] ?? 0);

        if ($minutes > 59 || $seconds > 59) {
            return null;
        }

        $total = $hours * 3600 + $minutes * 60 + $seconds;

        if ($total > 64800) {
            return null;
        }

        return $matches[1] === '-' ? -$total : $total;
    }
}
