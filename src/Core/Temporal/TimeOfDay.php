<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Temporal;

use DateTimeInterface;
use JsonSerializable;
use NoDiscard;
use Simtabi\Laranail\Chrono\Core\Exception\InvalidTemporalValue;
use Stringable;

/**
 * A time of day with no date and no timezone — opening hours, a daily reminder, a shift start.
 *
 * Named `TimeOfDay` rather than `LocalTime` because this package already has a `LocalTime`: the
 * result of resolving a wall-clock reading against a zone's daylight-saving rules. Two types with
 * one name meaning different things is a trap, and the DST one was here first.
 *
 * Note that "09:00 every day" is not a fixed number of seconds from midnight in a zone that observes
 * daylight saving — twice a year one day is 23 or 25 hours long. This type stays deliberately
 * ignorant of that; combine it with a `LocalDate` and a zone to get an instant, and the resolution
 * happens there where the policies live.
 */
final readonly class TimeOfDay implements JsonSerializable, Stringable
{
    private function __construct(
        public int $hour,
        public int $minute,
        public int $second,
    ) {}

    /** @throws InvalidTemporalValue */
    public static function of(int $hour, int $minute = 0, int $second = 0): self
    {
        if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59 || $second < 0 || $second > 59) {
            throw InvalidTemporalValue::time($hour, $minute, $second);
        }

        return new self($hour, $minute, $second);
    }

    /** `09:00`, `09:00:30`. */
    public static function parse(string $value): self
    {
        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', trim($value), $matches) !== 1) {
            throw InvalidTemporalValue::unparsable($value, 'H:i[:s]');
        }

        return self::of((int) $matches[1], (int) $matches[2], (int) ($matches[3] ?? 0));
    }

    public static function fromDateTime(DateTimeInterface $instant): self
    {
        return self::of(
            (int) $instant->format('G'),
            (int) $instant->format('i'),
            (int) $instant->format('s'),
        );
    }

    public static function midnight(): self
    {
        return new self(0, 0, 0);
    }

    public static function noon(): self
    {
        return new self(12, 0, 0);
    }

    /** Seconds since midnight — a wall-clock measure, not an elapsed one. */
    public function secondOfDay(): int
    {
        return $this->hour * 3600 + $this->minute * 60 + $this->second;
    }

    public function minuteOfDay(): int
    {
        return $this->hour * 60 + $this->minute;
    }

    /** Wraps at midnight, so 23:30 plus an hour is 00:30. */
    #[NoDiscard]
    public function plusMinutes(int $minutes): self
    {
        $total = (($this->secondOfDay() + $minutes * 60) % 86400 + 86400) % 86400;

        return new self(intdiv($total, 3600), intdiv($total % 3600, 60), $total % 60);
    }

    #[NoDiscard]
    public function plusHours(int $hours): self
    {
        return $this->plusMinutes($hours * 60);
    }

    #[NoDiscard]
    public function withSecond(int $second): self
    {
        return self::of($this->hour, $this->minute, $second);
    }

    public function compareTo(self $other): int
    {
        return $this->secondOfDay() <=> $other->secondOfDay();
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

    /** Inclusive of both ends. Ranges that cross midnight are handled. */
    public function isBetween(self $from, self $until): bool
    {
        if ($from->compareTo($until) <= 0) {
            return $this->compareTo($from) >= 0 && $this->compareTo($until) <= 0;
        }
        if ($this->compareTo($from) >= 0) {
            return true;
        }

        return $this->compareTo($until) <= 0;
    }

    public function toIso8601(): string
    {
        return sprintf('%02d:%02d:%02d', $this->hour, $this->minute, $this->second);
    }

    /** `09:00` when there are no seconds to show, `09:00:30` when there are. */
    public function toShortString(): string
    {
        return $this->second === 0
            ? sprintf('%02d:%02d', $this->hour, $this->minute)
            : $this->toIso8601();
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
