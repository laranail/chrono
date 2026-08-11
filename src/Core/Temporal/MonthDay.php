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
 * A day of a month with no year — an anniversary, a recurring holiday, a birthday.
 *
 * 29 February is valid here and has no year to be valid in, which is the whole reason the type
 * exists. `inYear()` decides what it means in a common year, and makes you choose rather than
 * quietly picking: some systems observe such an anniversary on 28 February, others on 1 March, and
 * the difference is visible to the person whose birthday it is.
 */
final readonly class MonthDay implements JsonSerializable, Stringable
{
    private function __construct(
        public int $month,
        public int $day,
    ) {}

    /** @throws InvalidTemporalValue */
    public static function of(int $month, int $day): self
    {
        if ($month < 1 || $month > 12) {
            throw InvalidTemporalValue::month($month);
        }

        // Validated against a leap year, so 29 February is accepted.
        $length = Month::from($month)->length(2024);

        if ($day < 1 || $day > $length) {
            throw InvalidTemporalValue::day($day, $month, 2024, $length);
        }

        return new self($month, $day);
    }

    /** `--06-15`, the ISO 8601 form, or the friendlier `06-15`. */
    public static function parse(string $value): self
    {
        if (preg_match('/^(?:--)?(\d{2})-(\d{2})$/', trim($value), $matches) !== 1) {
            throw InvalidTemporalValue::unparsable($value, '--mm-dd');
        }

        return self::of((int) $matches[1], (int) $matches[2]);
    }

    public static function fromDateTime(DateTimeInterface $instant): self
    {
        return self::of((int) $instant->format('n'), (int) $instant->format('j'));
    }

    public function monthEnum(): Month
    {
        return Month::from($this->month);
    }

    /** True only for 29 February, the one date that does not occur every year. */
    public function isLeapDay(): bool
    {
        return $this->month === 2 && $this->day === 29;
    }

    public function existsIn(int $year): bool
    {
        return $this->day <= $this->monthEnum()->length($year);
    }

    /**
     * This day in a given year.
     *
     * When the date does not occur — 29 February in a common year — `$leapDayFallback` decides.
     * `earlier` gives 28 February, `later` gives 1 March. There is no correct answer, only a choice,
     * so the parameter has no silent default beyond the more common convention.
     */
    #[NoDiscard]
    public function inYear(int $year, string $leapDayFallback = 'earlier'): LocalDate
    {
        if ($this->existsIn($year)) {
            return LocalDate::of($year, $this->month, $this->day);
        }

        return $leapDayFallback === 'later'
            ? LocalDate::of($year, 3, 1)
            : LocalDate::of($year, 2, 28);
    }

    public function compareTo(self $other): int
    {
        return [$this->month, $this->day] <=> [$other->month, $other->day];
    }

    public function equals(self $other): bool
    {
        return $this->compareTo($other) === 0;
    }

    public function toIso8601(): string
    {
        return sprintf('--%02d-%02d', $this->month, $this->day);
    }

    public function __toString(): string
    {
        return $this->toIso8601();
    }

    public function jsonSerialize(): string
    {
        return $this->toIso8601();
    }
}
