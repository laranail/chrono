<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Temporal;

use DateTimeInterface;
use JsonSerializable;
use NoDiscard;
use Simtabi\Laranail\Chrono\Core\Enums\Month;
use Simtabi\Laranail\Chrono\Core\Exception\InvalidTemporalValue;
use Stringable;

/**
 * A month of a year — a billing period, a card expiry, a reporting bucket.
 *
 * Carried as a `LocalDate` on the first of the month it invites arithmetic that silently means
 * something else: "the month after 31 January" has an answer, "31 January plus a month" is a
 * different question. This type has no day, so the ambiguity cannot arise.
 */
final readonly class YearMonth implements JsonSerializable, Stringable
{
    private function __construct(
        public int $year,
        public int $month,
    ) {}

    public function __toString(): string
    {
        return $this->toIso8601();
    }

    /** @throws InvalidTemporalValue */
    public static function of(int $year, int $month): self
    {
        if ($month < 1 || $month > 12) {
            throw InvalidTemporalValue::month($month);
        }

        return new self($year, $month);
    }

    /** `2026-06`. */
    public static function parse(string $value): self
    {
        if (preg_match('/^(\d{4})-(\d{2})$/', trim($value), $matches) !== 1) {
            throw InvalidTemporalValue::unparsable($value, 'Y-m');
        }

        return self::of((int) $matches[1], (int) $matches[2]);
    }

    public static function fromDateTime(DateTimeInterface $instant): self
    {
        return self::of((int) $instant->format('Y'), (int) $instant->format('n'));
    }

    public function monthEnum(): Month
    {
        return Month::from($this->month);
    }

    public function length(): int
    {
        return $this->monthEnum()->length($this->year);
    }

    public function quarter(): int
    {
        return $this->monthEnum()->quarter();
    }

    #[NoDiscard]
    public function plusMonths(int $months): self
    {
        $total = ($this->year * 12) + ($this->month - 1) + $months;

        return self::of(intdiv($total, 12), ($total % 12 + 12) % 12 + 1);
    }

    #[NoDiscard]
    public function minusMonths(int $months): self
    {
        return $this->plusMonths(-$months);
    }

    #[NoDiscard]
    public function plusYears(int $years): self
    {
        return self::of($this->year + $years, $this->month);
    }

    public function firstDay(): LocalDate
    {
        return LocalDate::of($this->year, $this->month, 1);
    }

    public function lastDay(): LocalDate
    {
        return LocalDate::of($this->year, $this->month, $this->length());
    }

    /** @return list<LocalDate> */
    public function days(): array
    {
        $days = [];

        for ($day = 1; $day <= $this->length(); $day++) {
            $days[] = LocalDate::of($this->year, $this->month, $day);
        }

        return $days;
    }

    public function contains(LocalDate $date): bool
    {
        return $date->year === $this->year && $date->month === $this->month;
    }

    public function compareTo(self $other): int
    {
        return [$this->year, $this->month] <=> [$other->year, $other->month];
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

    /** Whole months between the two, signed. */
    public function monthsUntil(self $other): int
    {
        return ($other->year * 12 + $other->month) - ($this->year * 12 + $this->month);
    }

    public function toIso8601(): string
    {
        return sprintf('%04d-%02d', $this->year, $this->month);
    }

    public function jsonSerialize(): string
    {
        return $this->toIso8601();
    }
}
