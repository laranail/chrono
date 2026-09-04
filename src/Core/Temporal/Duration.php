<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Temporal;

use NoDiscard;
use Stringable;
use DateInterval;
use JsonSerializable;
use DateTimeInterface;
use Simtabi\Laranail\Chrono\Core\Exception\InvalidTemporalValue;

/**
 * An elapsed length of time, in seconds.
 *
 * Deliberately not `DateInterval`. That type mixes elapsed units with calendar ones — it can hold
 * "1 month" and "23 hours" in the same object — which means it cannot answer "how long was that?"
 * without knowing where it started. This holds only seconds, so it always can.
 *
 * That distinction is not academic. `2026-03-08 00:00` to `2026-03-09 00:00` in New York is
 * `1 day, 0 hours` by `DateInterval` and **23 hours** by the clock, because the day the daylight
 * saving change falls on is 23 hours long. `between()` measures the second.
 */
final readonly class Duration implements JsonSerializable, Stringable
{
    private function __construct(public int $seconds) {}

    public function __toString(): string
    {
        return $this->toIso8601();
    }

    public static function ofSeconds(int $seconds): self
    {
        return new self($seconds);
    }

    public static function ofMinutes(int $minutes): self
    {
        return new self($minutes * 60);
    }

    public static function ofHours(int $hours): self
    {
        return new self($hours * 3600);
    }

    /** 24 hours exactly — an elapsed day, not a calendar one. */
    public static function ofDays(int $days): self
    {
        return new self($days * 86400);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    /** Elapsed time between two instants, from their timestamps rather than their fields. */
    public static function between(DateTimeInterface $from, DateTimeInterface $to): self
    {
        return new self($to->getTimestamp() - $from->getTimestamp());
    }

    /**
     * From an ISO 8601 duration — `PT1H30M`, `P1DT2H`.
     *
     * Only the elapsed designators are accepted. `P1M` is rejected because a month is not a length
     * of time until you know which month, and silently treating it as 30 days is how a subscription
     * ends on the wrong date.
     */
    public static function parse(string $value): self
    {
        $normalised = strtoupper(trim($value));

        if (preg_match('/^(-)?P(?:(\d+)W)?(?:(\d+)D)?(?:T(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?)?$/', $normalised, $m) !== 1
            || $normalised === 'P') {
            throw InvalidTemporalValue::unparsable($value, 'ISO 8601 duration (weeks, days, hours, minutes, seconds)');
        }

        $seconds = ((int) ($m[2] ?? 0)) * 604800
            + ((int) ($m[3] ?? 0)) * 86400
            + ((int) ($m[4] ?? 0)) * 3600
            + ((int) ($m[5] ?? 0)) * 60
            + ((int) ($m[6] ?? 0));

        return new self(($m[1] ?? '') === '-' ? -$seconds : $seconds);
    }

    public function minutes(): int
    {
        return intdiv($this->seconds, 60);
    }

    public function hours(): int
    {
        return intdiv($this->seconds, 3600);
    }

    public function days(): int
    {
        return intdiv($this->seconds, 86400);
    }

    public function isZero(): bool
    {
        return $this->seconds === 0;
    }

    public function isNegative(): bool
    {
        return $this->seconds < 0;
    }

    #[NoDiscard]
    public function plus(self $other): self
    {
        return new self($this->seconds + $other->seconds);
    }

    #[NoDiscard]
    public function minus(self $other): self
    {
        return new self($this->seconds - $other->seconds);
    }

    #[NoDiscard]
    public function multipliedBy(int $factor): self
    {
        return new self($this->seconds * $factor);
    }

    #[NoDiscard]
    public function absolute(): self
    {
        return new self(abs($this->seconds));
    }

    #[NoDiscard]
    public function negated(): self
    {
        return new self(-$this->seconds);
    }

    public function compareTo(self $other): int
    {
        return $this->seconds <=> $other->seconds;
    }

    public function equals(self $other): bool
    {
        return $this->seconds === $other->seconds;
    }

    /** @return array{days: int, hours: int, minutes: int, seconds: int} */
    public function parts(): array
    {
        $remaining = abs($this->seconds);

        return [
            'days'    => intdiv($remaining, 86400),
            'hours'   => intdiv($remaining % 86400, 3600),
            'minutes' => intdiv($remaining % 3600, 60),
            'seconds' => $remaining % 60,
        ];
    }

    public function toDateInterval(): DateInterval
    {
        $interval = new DateInterval($this->toIso8601($absolute = true));
        $interval->invert = $this->seconds < 0 ? 1 : 0;

        return $interval;
    }

    public function toIso8601(bool $absolute = false): string
    {
        $parts = $this->parts();
        $sign = ! $absolute && $this->seconds < 0 ? '-' : '';

        $date = $parts['days'] > 0 ? $parts['days'] . 'D' : '';

        $time = ($parts['hours'] > 0 ? $parts['hours'] . 'H' : '')
            . ($parts['minutes'] > 0 ? $parts['minutes'] . 'M' : '')
            . ($parts['seconds'] > 0 ? $parts['seconds'] . 'S' : '');

        if ($date === '' && $time === '') {
            return $sign . 'PT0S';
        }

        return $sign . 'P' . $date . ($time === '' ? '' : 'T' . $time);
    }

    /** `1:30:00`, `72:00:00` — hours are not wrapped, because elapsed time does not wrap. */
    public function toClockString(): string
    {
        $parts = $this->parts();

        return sprintf(
            '%s%d:%02d:%02d',
            $this->seconds < 0 ? '-' : '',
            $parts['days'] * 24 + $parts['hours'],
            $parts['minutes'],
            $parts['seconds'],
        );
    }

    public function jsonSerialize(): int
    {
        return $this->seconds;
    }
}
