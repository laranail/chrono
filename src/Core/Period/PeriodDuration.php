<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Period;

use DateInterval;
use JsonSerializable;
use Stringable;

/**
 * How long a period is, as a calendar interval plus a comparable length.
 *
 * Two things are wanted from a period's duration and they disagree. "How long
 * was it?" wants a number that can be compared and summed; "what does it look
 * like?" wants months and days, which are not a fixed number of seconds. This
 * carries both, and compares on the count of precision steps, which is the only
 * one of the two that is well ordered.
 */
final readonly class PeriodDuration implements JsonSerializable, Stringable
{
    public function __construct(
        public DateInterval $interval,
        public int $steps,
        public Precision $precision,
    ) {}

    /** Whole steps of the period's own precision, the unit `length()` counts in. */
    public function inSteps(): int
    {
        return $this->steps;
    }

    public function equals(self $other): bool
    {
        return $this->comparable() === $other->comparable();
    }

    public function isLongerThan(self $other): bool
    {
        return $this->comparable() > $other->comparable();
    }

    public function isShorterThan(self $other): bool
    {
        return $this->comparable() < $other->comparable();
    }

    /** @return array{steps: int, precision: string, days: int, human: string} */
    public function toArray(): array
    {
        return [
            'steps' => $this->steps,
            'precision' => $this->precision->value === 0 ? 'second' : strtolower($this->precision->name),
            'days' => (int) $this->interval->days,
            'human' => (string) $this,
        ];
    }

    /** @return array{steps: int, precision: string, days: int, human: string} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function __toString(): string
    {
        $unit = strtolower($this->precision->name);

        return $this->steps === 1
            ? "1 {$unit}"
            : "{$this->steps} {$unit}s";
    }

    /**
     * Durations are only comparable within one precision.
     *
     * Comparing "3 months" with "90 days" needs a calendar and a starting point,
     * neither of which a duration has, so this pairs the count with the precision
     * rather than pretending a month is a fixed length.
     *
     * @return array{int, int}
     */
    private function comparable(): array
    {
        return [$this->precision->value, $this->steps];
    }
}
