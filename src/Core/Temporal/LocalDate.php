<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Temporal;

use NoDiscard;
use Stringable;
use DateTimeZone;
use JsonSerializable;
use DateTimeImmutable;
use DateTimeInterface;
use Simtabi\Laranail\Chrono\Core\Enums\Month;
use Simtabi\Laranail\Chrono\Core\Enums\DayOfWeek;
use Simtabi\Laranail\Chrono\Core\Exception\InvalidTemporalValue;

/**
 * A date with no time and no timezone — a birthday, an invoice date, a public holiday.
 *
 * PHP has no such type, so these are normally carried as a `DateTimeImmutable` at midnight, which
 * is a different thing and behaves differently. Midnight is an instant, so it belongs to a zone; a
 * birthday does not. Convert one to another zone and it can land on the previous day, which is how
 * a date shifts by one for users in the wrong hemisphere.
 *
 * This type has no instant, so it cannot move. It becomes one only when you name a zone.
 */
final readonly class LocalDate implements JsonSerializable, Stringable
{
    private function __construct(
        public int $year,
        public int $month,
        public int $day,
    ) {}

    public function __toString(): string
    {
        return $this->toIso8601();
    }

    /** @throws InvalidTemporalValue when the date does not exist, e.g. 31 February */
    public static function of(int $year, int $month, int $day): self
    {
        if ($month < 1 || $month > 12) {
            throw InvalidTemporalValue::month($month);
        }

        $length = Month::from($month)->length($year);

        if ($day < 1 || $day > $length) {
            throw InvalidTemporalValue::day($day, $month, $year, $length);
        }

        return new self($year, $month, $day);
    }

    /** `2026-06-15`. */
    public static function parse(string $value): self
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', trim($value), $matches) !== 1) {
            throw InvalidTemporalValue::unparsable($value, 'Y-m-d');
        }

        return self::of((int) $matches[1], (int) $matches[2], (int) $matches[3]);
    }

    /** Reads only the date fields; the instant's zone is discarded, which is the point. */
    public static function fromDateTime(DateTimeInterface $instant): self
    {
        return self::of(
            (int) $instant->format('Y'),
            (int) $instant->format('n'),
            (int) $instant->format('j'),
        );
    }

    public function monthEnum(): Month
    {
        return Month::from($this->month);
    }

    public function dayOfWeek(): DayOfWeek
    {
        return DayOfWeek::from((int) $this->toDateTime()->format('N'));
    }

    public function dayOfYear(): int
    {
        return (int) $this->toDateTime()->format('z') + 1;
    }

    /**
     * The ISO week-numbering year, which is not always the calendar year.
     *
     * `2027-01-01` belongs to ISO week 53 of **2026**, and `2021-01-01` to week 53 of 2020. Reporting
     * that groups by `format('W')` and `format('Y')` separately produces a phantom week 53 in the
     * wrong year; `format('o')` is the one that agrees with the week number.
     *
     * @return array{year: int, week: int}
     */
    public function isoWeek(): array
    {
        $instant = $this->toDateTime();

        return ['year' => (int) $instant->format('o'), 'week' => (int) $instant->format('W')];
    }

    public function isLeapYear(): bool
    {
        return Month::isLeapYear($this->year);
    }

    public function isWeekend(): bool
    {
        return $this->dayOfWeek()->isWeekend();
    }

    // ── movement ────────────────────────────────────────────────────────────────────────────

    #[NoDiscard]
    public function plusDays(int $days): self
    {
        return self::fromDateTime($this->toDateTime()->modify(sprintf('%+d days', $days)));
    }

    #[NoDiscard]
    public function minusDays(int $days): self
    {
        return $this->plusDays(-$days);
    }

    /**
     * Add months, clamping to the end of the target month.
     *
     * 31 January plus one month is 28 or 29 February, not 2 or 3 March. PHP's own `modify('+1
     * month')` overflows into the next month, which is arithmetically defensible and almost never
     * what a billing cycle means.
     */
    #[NoDiscard]
    public function plusMonths(int $months): self
    {
        $total = ($this->year * 12) + ($this->month - 1) + $months;
        $year = intdiv($total, 12);
        $month = ($total % 12) + 1;

        if ($month < 1) {
            $month += 12;
            $year--;
        }

        return self::of($year, $month, min($this->day, Month::from($month)->length($year)));
    }

    #[NoDiscard]
    public function minusMonths(int $months): self
    {
        return $this->plusMonths(-$months);
    }

    /** Clamps too: 29 February plus one year is 28 February. */
    #[NoDiscard]
    public function plusYears(int $years): self
    {
        $year = $this->year + $years;

        return self::of($year, $this->month, min($this->day, Month::from($this->month)->length($year)));
    }

    #[NoDiscard]
    public function minusYears(int $years): self
    {
        return $this->plusYears(-$years);
    }

    #[NoDiscard]
    public function withDay(int $day): self
    {
        return self::of($this->year, $this->month, $day);
    }

    #[NoDiscard]
    public function firstOfMonth(): self
    {
        return self::of($this->year, $this->month, 1);
    }

    #[NoDiscard]
    public function lastOfMonth(): self
    {
        return self::of($this->year, $this->month, $this->monthEnum()->length($this->year));
    }

    // ── comparison ──────────────────────────────────────────────────────────────────────────

    public function compareTo(self $other): int
    {
        return [$this->year, $this->month, $this->day] <=> [$other->year, $other->month, $other->day];
    }

    public function equals(self $other): bool
    {
        return $this->compareTo($other) === 0;
    }

    public function isBefore(self $other): bool
    {
        return $this->compareTo($other) < 0;
    }

    public function isAfter(self $other): bool
    {
        return $this->compareTo($other) > 0;
    }

    /** Whole days between two dates, signed. */
    public function daysUntil(self $other): int
    {
        return (int) $this->toDateTime()->diff($other->toDateTime())->format('%r%a');
    }

    // ── conversion ──────────────────────────────────────────────────────────────────────────

    /** Midnight in the given zone — the moment this date acquires an instant, and a zone. */
    #[NoDiscard]
    public function atStartOfDay(?DateTimeZone $zone = null): DateTimeImmutable
    {
        return new DateTimeImmutable(
            sprintf('%04d-%02d-%02d 00:00:00', $this->year, $this->month, $this->day),
            $zone ?? new DateTimeZone('UTC'),
        );
    }

    #[NoDiscard]
    public function atTime(TimeOfDay $time, ?DateTimeZone $zone = null): DateTimeImmutable
    {
        return new DateTimeImmutable(
            sprintf('%04d-%02d-%02d %s', $this->year, $this->month, $this->day, $time->toIso8601()),
            $zone ?? new DateTimeZone('UTC'),
        );
    }

    public function toYearMonth(): YearMonth
    {
        return YearMonth::of($this->year, $this->month);
    }

    public function toMonthDay(): MonthDay
    {
        return MonthDay::of($this->month, $this->day);
    }

    public function toIso8601(): string
    {
        return sprintf('%04d-%02d-%02d', $this->year, $this->month, $this->day);
    }

    public function format(string $pattern): string
    {
        return $this->toDateTime()->format($pattern);
    }

    public function jsonSerialize(): string
    {
        return $this->toIso8601();
    }

    /** UTC midnight, used internally for calendar arithmetic PHP already knows how to do. */
    private function toDateTime(): DateTimeImmutable
    {
        return $this->atStartOfDay();
    }
}
