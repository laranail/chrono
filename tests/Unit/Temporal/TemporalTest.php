<?php

declare(strict_types=1);

use Simtabi\Laranail\Chrono\Core\Enums\DayOfWeek;
use Simtabi\Laranail\Chrono\Core\Enums\Month;
use Simtabi\Laranail\Chrono\Core\Exception\InvalidTemporalValue;
use Simtabi\Laranail\Chrono\Core\Temporal\Duration;
use Simtabi\Laranail\Chrono\Core\Temporal\LocalDate;
use Simtabi\Laranail\Chrono\Core\Temporal\MonthDay;
use Simtabi\Laranail\Chrono\Core\Temporal\TimeOfDay;
use Simtabi\Laranail\Chrono\Core\Temporal\YearMonth;

describe('LocalDate', function (): void {
    it('refuses a date that does not exist', function (): void {
        LocalDate::of(2026, 2, 30);
    })->throws(InvalidTemporalValue::class);

    it('accepts 29 February only in a leap year', function (): void {
        expect(LocalDate::of(2024, 2, 29)->toIso8601())->toBe('2024-02-29')
            ->and(fn (): LocalDate => LocalDate::of(2026, 2, 29))->toThrow(InvalidTemporalValue::class);
    });

    /**
     * The reason the type exists. A date carried as a UTC midnight instant lands on the previous
     * day in any zone west of UTC; a LocalDate has no instant, so it cannot move.
     */
    it('has no instant until a zone is named', function (): void {
        $date = LocalDate::of(2026, 6, 15);

        expect($date->atStartOfDay(new DateTimeZone('Pacific/Kiritimati'))->format('Y-m-d'))->toBe('2026-06-15')
            ->and($date->atStartOfDay(new DateTimeZone('Pacific/Midway'))->format('Y-m-d'))->toBe('2026-06-15')
            ->and($date->toIso8601())->toBe('2026-06-15');
    });

    /** PHP's own modify('+1 month') overflows into March; a billing cycle almost never means that. */
    it('clamps month arithmetic instead of overflowing', function (): void {
        expect(LocalDate::of(2026, 1, 31)->plusMonths(1)->toIso8601())->toBe('2026-02-28')
            ->and(LocalDate::of(2024, 1, 31)->plusMonths(1)->toIso8601())->toBe('2024-02-29')
            ->and(LocalDate::of(2024, 2, 29)->plusYears(1)->toIso8601())->toBe('2025-02-28');

        // What PHP does with the same question.
        expect(new DateTimeImmutable('2026-01-31')->modify('+1 month')->format('Y-m-d'))->toBe('2026-03-03');
    });

    /**
     * The ISO week-numbering year is not the calendar year. Grouping a report by format('W') and
     * format('Y') separately invents a week 53 in the wrong year.
     */
    it('reports the ISO week-numbering year, not the calendar year', function (): void {
        expect(LocalDate::of(2027, 1, 1)->isoWeek())->toBe(['year' => 2026, 'week' => 53])
            ->and(LocalDate::of(2021, 1, 1)->isoWeek())->toBe(['year' => 2020, 'week' => 53]);
    });

    it('moves and compares', function (): void {
        $date = LocalDate::of(2026, 6, 15);

        expect($date->plusDays(20)->toIso8601())->toBe('2026-07-05')
            ->and($date->dayOfWeek())->toBe(DayOfWeek::Monday)
            ->and($date->lastOfMonth()->day)->toBe(30)
            ->and($date->daysUntil(LocalDate::of(2026, 6, 25)))->toBe(10)
            ->and($date->isBefore(LocalDate::of(2026, 6, 16)))->toBeTrue();
    });

    it('round-trips through ISO 8601', function (): void {
        expect(LocalDate::parse('2026-06-15')->toIso8601())->toBe('2026-06-15')
            ->and(fn (): LocalDate => LocalDate::parse('15/06/2026'))->toThrow(InvalidTemporalValue::class);
    });
});

describe('TimeOfDay', function (): void {
    it('validates and parses', function (): void {
        expect(TimeOfDay::parse('09:00')->toShortString())->toBe('09:00')
            ->and(TimeOfDay::parse('09:00:30')->toIso8601())->toBe('09:00:30')
            ->and(fn (): TimeOfDay => TimeOfDay::of(24, 0))->toThrow(InvalidTemporalValue::class);
    });

    it('wraps at midnight', function (): void {
        expect(TimeOfDay::of(23, 30)->plusHours(1)->toShortString())->toBe('00:30')
            ->and(TimeOfDay::of(0, 15)->plusMinutes(-30)->toShortString())->toBe('23:45');
    });

    /** Opening hours that run past midnight are the common case, not the exception. */
    it('handles a range that crosses midnight', function (): void {
        $late = TimeOfDay::of(23, 0);
        $early = TimeOfDay::of(2, 0);

        expect(TimeOfDay::of(23, 30)->isBetween($late, $early))->toBeTrue()
            ->and(TimeOfDay::of(1, 0)->isBetween($late, $early))->toBeTrue()
            ->and(TimeOfDay::of(12, 0)->isBetween($late, $early))->toBeFalse();
    });
});

describe('YearMonth', function (): void {
    it('does month arithmetic without a day to get in the way', function (): void {
        expect(YearMonth::of(2026, 1)->plusMonths(13)->toIso8601())->toBe('2027-02')
            ->and(YearMonth::of(2026, 1)->minusMonths(1)->toIso8601())->toBe('2025-12')
            ->and(YearMonth::of(2026, 1)->monthsUntil(YearMonth::of(2027, 3)))->toBe(14);
    });

    it('knows its own length', function (): void {
        expect(YearMonth::of(2026, 2)->length())->toBe(28)
            ->and(YearMonth::of(2024, 2)->length())->toBe(29)
            ->and(YearMonth::of(2024, 2)->days())->toHaveCount(29)
            ->and(YearMonth::of(2026, 6)->lastDay()->toIso8601())->toBe('2026-06-30')
            ->and(YearMonth::of(2026, 6)->quarter())->toBe(2);
    });
});

describe('MonthDay', function (): void {
    /** The reason the type exists: 29 February has no year to be valid in. */
    it('accepts the leap day and makes you choose what it means', function (): void {
        $leapDay = MonthDay::of(2, 29);

        expect($leapDay->isLeapDay())->toBeTrue()
            ->and($leapDay->existsIn(2024))->toBeTrue()
            ->and($leapDay->existsIn(2026))->toBeFalse()
            ->and($leapDay->inYear(2024)->toIso8601())->toBe('2024-02-29')
            ->and($leapDay->inYear(2026)->toIso8601())->toBe('2026-02-28')
            ->and($leapDay->inYear(2026, 'later')->toIso8601())->toBe('2026-03-01');
    });

    it('round-trips the ISO form', function (): void {
        expect(MonthDay::parse('--06-15')->toIso8601())->toBe('--06-15')
            ->and(MonthDay::parse('06-15')->equals(MonthDay::of(6, 15)))->toBeTrue();
    });
});

describe('Duration', function (): void {
    /**
     * The distinction the type exists for: DateInterval mixes elapsed and calendar units, so it
     * answers "1 day" for a day that was 23 hours long.
     */
    it('measures elapsed time where DateInterval measures calendar fields', function (): void {
        $zone = new DateTimeZone('America/New_York');
        $from = new DateTimeImmutable('2026-03-08 00:00', $zone);
        $to = new DateTimeImmutable('2026-03-09 00:00', $zone);

        expect($from->diff($to)->d)->toBe(1)
            ->and($from->diff($to)->h)->toBe(0)
            ->and(Duration::between($from, $to)->hours())->toBe(23);
    });

    it('parses and renders ISO 8601', function (): void {
        expect(Duration::parse('PT1H30M')->seconds)->toBe(5400)
            ->and(Duration::parse('P1DT2H')->seconds)->toBe(93600)
            ->and(Duration::ofSeconds(5400)->toIso8601())->toBe('PT1H30M')
            ->and(Duration::zero()->toIso8601())->toBe('PT0S')
            ->and(Duration::ofSeconds(-90)->toIso8601())->toBe('-PT1M30S');
    });

    /** A month is not a length of time until you know which month. */
    it('refuses calendar units', function (): void {
        Duration::parse('P1M');
    })->throws(InvalidTemporalValue::class);

    it('does arithmetic and renders a clock string', function (): void {
        expect(Duration::ofHours(1)->plus(Duration::ofMinutes(30))->toClockString())->toBe('1:30:00')
            ->and(Duration::ofDays(3)->toClockString())->toBe('72:00:00')
            ->and(Duration::ofHours(2)->minus(Duration::ofHours(3))->isNegative())->toBeTrue()
            ->and(Duration::ofMinutes(90)->parts())->toBe(['days' => 0, 'hours' => 1, 'minutes' => 30, 'seconds' => 0]);
    });
});

describe('the enums', function (): void {
    /** PHP's own 'w' format counts Sunday as 0; ISO counts Monday as 1. */
    it('converts between the two weekday conventions', function (): void {
        expect(DayOfWeek::fromPhp(0))->toBe(DayOfWeek::Sunday)
            ->and(DayOfWeek::Sunday->toPhp())->toBe(0)
            ->and(DayOfWeek::Monday->value)->toBe(1)
            ->and(DayOfWeek::Sunday->isWeekend())->toBeTrue()
            ->and(DayOfWeek::Sunday->next())->toBe(DayOfWeek::Monday);
    });

    it('knows month lengths and leap years', function (): void {
        expect(Month::February->length(2024))->toBe(29)
            ->and(Month::February->length(2026))->toBe(28)
            ->and(Month::February->length(1900))->toBe(28)   // century, not a leap year
            ->and(Month::February->length(2000))->toBe(29)   // fourth century, is one
            ->and(Month::December->next())->toBe(Month::January)
            ->and(Month::June->quarter())->toBe(2);
    });
});
