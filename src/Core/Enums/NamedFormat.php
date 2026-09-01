<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Enums;

/**
 * The named formats an application actually reaches for, superseding the loose catalogue
 * `simtabi/pheg` carried in `config/supports.php`.
 *
 * Two families, and the difference matters:
 *
 *   **Machine formats** (`Iso8601`, `Rfc2822`, `Sql`, …) are fixed patterns. They must not vary by
 *   locale — an ISO 8601 timestamp is the same string in Cairo as in Tokyo, and localising one is
 *   how a date lands in a database in a format nothing can read back.
 *
 *   **Human formats** (`ShortDate`, `LongDateTime`, …) resolve to an ICU *skeleton*, which the
 *   formatter turns into the pattern that locale actually uses. `yMMMd` becomes `MMM d, y` in
 *   en_US, `d. MMM y` in de_DE and `y年M月d日` in ja_JP. Hard-coding `M j, Y` instead gives every
 *   locale the American ordering.
 */
enum NamedFormat: string
{
    // ── machine: never localised ────────────────────────────────────────────────────────────
    case Iso8601 = 'iso8601';
    case Rfc2822 = 'rfc2822';
    case Rfc3339 = 'rfc3339';
    case Sql = 'sql';
    case SqlDate = 'sql_date';
    case SqlTime = 'sql_time';
    case Atom = 'atom';
    case Cookie = 'cookie';
    case Timestamp = 'timestamp';

    // ── human: locale-aware skeletons ───────────────────────────────────────────────────────
    case ShortDate = 'short_date';
    case MediumDate = 'medium_date';
    case LongDate = 'long_date';
    case FullDate = 'full_date';
    case ShortTime = 'short_time';
    case MediumTime = 'medium_time';
    case ShortDateTime = 'short_date_time';
    case MediumDateTime = 'medium_date_time';
    case LongDateTime = 'long_date_time';
    case DayMonth = 'day_month';
    case MonthYear = 'month_year';
    case WeekdayDate = 'weekday_date';

    public function isMachineReadable(): bool
    {
        return $this->pattern() !== null;
    }

    /** The fixed pattern for a machine format, or null when the format is locale-dependent. */
    public function pattern(): ?string
    {
        return match ($this) {
            self::Iso8601, self::Atom, self::Rfc3339 => 'Y-m-d\TH:i:sP',
            self::Rfc2822 => 'D, d M Y H:i:s O',
            self::Sql => 'Y-m-d H:i:s',
            self::SqlDate => 'Y-m-d',
            self::SqlTime => 'H:i:s',
            self::Cookie => 'l, d-M-Y H:i:s T',
            self::Timestamp => 'U',
            default => null,
        };
    }

    /**
     * The ICU skeleton for a human format.
     *
     * A skeleton names the *fields* wanted, not their order or separators — `IntlDatePatternGenerator`
     * supplies those per locale.
     */
    public function skeleton(): ?string
    {
        return match ($this) {
            self::ShortDate => 'yMd',
            self::MediumDate => 'yMMMd',
            self::LongDate => 'yMMMMd',
            self::FullDate => 'yMMMMEEEEd',
            self::ShortTime => 'jm',
            self::MediumTime => 'jms',
            self::ShortDateTime => 'yMdjm',
            self::MediumDateTime => 'yMMMdjm',
            self::LongDateTime => 'yMMMMdjms',
            self::DayMonth => 'MMMd',
            self::MonthYear => 'yMMMM',
            self::WeekdayDate => 'EEEEyMMMMd',
            default => null,
        };
    }
}
