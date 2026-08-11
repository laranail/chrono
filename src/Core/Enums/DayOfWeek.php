<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Enums;

/**
 * A day of the week, numbered ISO-8601: Monday is 1, Sunday is 7.
 *
 * ISO because that is what `DateTimeInterface::format('N')` returns and what every standard means
 * by "day 1". PHP's own `format('w')` disagrees — it counts Sunday as 0 — so `fromPhp()` exists to
 * convert rather than leaving callers to guess which convention a bare integer follows.
 */
enum DayOfWeek: int
{
    case Monday = 1;
    case Tuesday = 2;
    case Wednesday = 3;
    case Thursday = 4;
    case Friday = 5;
    case Saturday = 6;
    case Sunday = 7;

    /** From PHP's `w` format, where Sunday is 0. */
    public static function fromPhp(int $dayOfWeek): self
    {
        return self::from($dayOfWeek === 0 ? 7 : $dayOfWeek);
    }

    /** As PHP's `w` format expects it. */
    public function toPhp(): int
    {
        return $this === self::Sunday ? 0 : $this->value;
    }

    public function name(): string
    {
        return $this->name;
    }

    /** Saturday and Sunday. Note this is a convention, not a fact — many countries differ. */
    public function isWeekend(): bool
    {
        return $this === self::Saturday || $this === self::Sunday;
    }

    public function isWeekday(): bool
    {
        return ! $this->isWeekend();
    }

    public function next(): self
    {
        return self::from($this->value === 7 ? 1 : $this->value + 1);
    }

    public function previous(): self
    {
        return self::from($this->value === 1 ? 7 : $this->value - 1);
    }
}
