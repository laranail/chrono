<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Period;

use DateTimeImmutable;
use DateTimeInterface;
use Simtabi\Laranail\Enumerator\Attributes\Label;
use Simtabi\Laranail\Enumerator\Concerns\HasEnumeratorBehavior;
use Simtabi\Laranail\Enumerator\Contracts\Enumerator;

/**
 * How finely a period measures time.
 *
 * Precision is what makes two periods comparable at all. "March" and
 * "2026-03-14 09:31:07" only overlap in a way anyone can reason about once both
 * are rounded to the same unit, so a period rounds its own boundaries on
 * construction and refuses to be compared with one that rounded differently.
 *
 * The values are bit masks rather than an ordering, so a coarser precision is a
 * subset of a finer one and `Month` can be tested against `Day` with `&` rather
 * than a table of comparisons.
 */
enum Precision: int implements Enumerator
{
    use HasEnumeratorBehavior;

    #[Label('Year')] case Year = 0b100000;

    #[Label('Month')] case Month = 0b110000;

    #[Label('Day')] case Day = 0b111000;

    #[Label('Hour')] case Hour = 0b111100;

    #[Label('Minute')] case Minute = 0b111110;

    #[Label('Second')] case Second = 0b111111;

    /** The `date()` format that keeps only what this precision measures. */
    public function format(): string
    {
        return match ($this) {
            self::Year => 'Y-01-01 00:00:00',
            self::Month => 'Y-m-01 00:00:00',
            self::Day => 'Y-m-d 00:00:00',
            self::Hour => 'Y-m-d H:00:00',
            self::Minute => 'Y-m-d H:i:00',
            self::Second => 'Y-m-d H:i:s',
        };
    }

    /** The interval one step of this precision advances. */
    public function interval(): string
    {
        return match ($this) {
            self::Year => '+1 year',
            self::Month => '+1 month',
            self::Day => '+1 day',
            self::Hour => '+1 hour',
            self::Minute => '+1 minute',
            self::Second => '+1 second',
        };
    }

    /** Round a moment down to this precision, keeping its timezone. */
    public function round(DateTimeInterface $date): DateTimeImmutable
    {
        $rounded = DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $date->format($this->format()),
            $date->getTimezone(),
        );

        // createFromFormat carries the current microsecond unless told otherwise,
        // which would make two periods rounded to the same second unequal.
        return $rounded === false
            ? DateTimeImmutable::createFromInterface($date)
            : $rounded->setTime(
                (int) $rounded->format('H'),
                (int) $rounded->format('i'),
                (int) $rounded->format('s'),
            );
    }

    /** Whether this precision measures at least as finely as another. */
    public function isAsFineAs(self $other): bool
    {
        return ($this->value & $other->value) === $other->value;
    }
}
