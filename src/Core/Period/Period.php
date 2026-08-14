<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Period;

use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use JsonSerializable;
use Simtabi\Laranail\Chrono\Core\Exception\InvalidPeriod;
use Stringable;

/**
 * A span of time between two moments, at a stated precision.
 *
 * Immutable, and rounded to its precision on construction, so two periods that
 * measure the same unit can always be compared. Every operation returns new
 * periods rather than mutating, which is what makes the collection operations
 * safe to chain.
 *
 * Boundaries decide whether the endpoints belong to the span, which is the
 * difference between two appointments colliding and merely meeting. See
 * {@see Boundaries}.
 */
final readonly class Period implements JsonSerializable, Stringable
{
    private DateTimeImmutable $includedStart;

    private DateTimeImmutable $includedEnd;

    public function __construct(
        public DateTimeImmutable $start,
        public DateTimeImmutable $end,
        public Precision $precision = Precision::Day,
        public Boundaries $boundaries = Boundaries::IncludeAll,
    ) {
        if ($start > $end) {
            throw InvalidPeriod::endBeforeStart($start, $end);
        }

        // The included endpoints are what every comparison actually uses. Working
        // them out once here means no operation has to remember to.
        $this->includedStart = $boundaries->startExcluded()
            ? $start->modify($precision->interval())
            : $start;

        $this->includedEnd = $boundaries->endExcluded()
            ? $end->modify('-' . ltrim($precision->interval(), '+'))
            : $end;
    }

    /**
     * Build a period from anything date-like, rounding to the given precision.
     *
     * @throws InvalidPeriod
     */
    public static function make(
        DateTimeInterface|string $start,
        DateTimeInterface|string $end,
        Precision $precision = Precision::Day,
        Boundaries $boundaries = Boundaries::IncludeAll,
    ): self {
        return new self(
            $precision->round(self::moment($start)),
            $precision->round(self::moment($end)),
            $precision,
            $boundaries,
        );
    }

    public function startsBefore(DateTimeInterface $date): bool
    {
        return $this->includedStart < $this->round($date);
    }

    public function startsBeforeOrAt(DateTimeInterface $date): bool
    {
        return $this->includedStart <= $this->round($date);
    }

    public function startsAfter(DateTimeInterface $date): bool
    {
        return $this->includedStart > $this->round($date);
    }

    public function startsAfterOrAt(DateTimeInterface $date): bool
    {
        return $this->includedStart >= $this->round($date);
    }

    public function startsAt(DateTimeInterface $date): bool
    {
        return $this->includedStart->getTimestamp() === $this->round($date)->getTimestamp();
    }

    public function endsBefore(DateTimeInterface $date): bool
    {
        return $this->includedEnd < $this->round($date);
    }

    public function endsBeforeOrAt(DateTimeInterface $date): bool
    {
        return $this->includedEnd <= $this->round($date);
    }

    public function endsAfter(DateTimeInterface $date): bool
    {
        return $this->includedEnd > $this->round($date);
    }

    public function endsAfterOrAt(DateTimeInterface $date): bool
    {
        return $this->includedEnd >= $this->round($date);
    }

    public function endsAt(DateTimeInterface $date): bool
    {
        return $this->includedEnd->getTimestamp() === $this->round($date)->getTimestamp();
    }

    public function overlapsWith(self $other): bool
    {
        $this->assertSamePrecision($other);

        return $this->includedStart <= $other->includedEnd
            && $this->includedEnd >= $other->includedStart;
    }

    /**
     * Whether the two meet exactly, with nothing between and no shared moment.
     */
    public function touchesWith(self $other): bool
    {
        $this->assertSamePrecision($other);

        $step = ltrim($this->precision->interval(), '+');
        if ($this->includedEnd->modify('+' . $step)->getTimestamp() === $other->includedStart->getTimestamp()) {
            return true;
        }

        return $this->includedStart->modify('-' . $step)->getTimestamp() === $other->includedEnd->getTimestamp();
    }

    public function contains(DateTimeInterface|self $other): bool
    {
        if ($other instanceof self) {
            $this->assertSamePrecision($other);

            return $this->includedStart <= $other->includedStart
                && $this->includedEnd >= $other->includedEnd;
        }

        $moment = $this->round($other);

        return $moment >= $this->includedStart && $moment <= $this->includedEnd;
    }

    public function equals(self $other): bool
    {
        return $this->precision === $other->precision
            && $this->includedStart->getTimestamp() === $other->includedStart->getTimestamp()
            && $this->includedEnd->getTimestamp() === $other->includedEnd->getTimestamp();
    }

    /** The span shared by this period and every one given, if there is one. */
    public function overlap(self ...$others): ?self
    {
        $overlap = $this;

        foreach ($others as $other) {
            $overlap = $overlap->overlapWith($other);

            if (! $overlap instanceof Period) {
                return null;
            }
        }

        return $overlap;
    }

    /** Every overlap with any of the given periods, separately. */
    public function overlapAny(self ...$others): PeriodCollection
    {
        $overlaps = new PeriodCollection;

        foreach ($others as $other) {
            $overlap = $this->overlapWith($other);

            if ($overlap instanceof Period) {
                $overlaps = $overlaps->add($overlap);
            }
        }

        return $overlaps;
    }

    /** What is left of this period once the others are removed. */
    public function subtract(self ...$others): PeriodCollection
    {
        $others = array_values(array_filter(
            $others,
            $this->overlapsWith(...),
        ));

        if ($others === []) {
            return new PeriodCollection($this);
        }

        usort($others, fn (self $a, self $b): int => $a->includedStart <=> $b->includedStart);

        $remaining = new PeriodCollection;
        $cursor = $this->includedStart;
        $step = ltrim($this->precision->interval(), '+');

        foreach ($others as $other) {
            if ($other->includedStart > $cursor) {
                $remaining = $remaining->add(new self(
                    $cursor,
                    $other->includedStart->modify('-' . $step),
                    $this->precision,
                ));
            }

            if ($other->includedEnd >= $cursor) {
                $cursor = $other->includedEnd->modify('+' . $step);
            }
        }

        if ($cursor <= $this->includedEnd) {
            return $remaining->add(new self($cursor, $this->includedEnd, $this->precision));
        }

        return $remaining;
    }

    /** The span between the two, when they neither overlap nor touch. */
    public function gap(self $other): ?self
    {
        $this->assertSamePrecision($other);

        if ($this->overlapsWith($other) || $this->touchesWith($other)) {
            return null;
        }

        $step = ltrim($this->precision->interval(), '+');

        [$first, $second] = $this->includedStart < $other->includedStart
            ? [$this, $other]
            : [$other, $this];

        return new self(
            $first->includedEnd->modify('+' . $step),
            $second->includedStart->modify('-' . $step),
            $this->precision,
        );
    }

    /** What belongs to one period or the other, but not to both. */
    public function diffSymmetric(self $other): PeriodCollection
    {
        $overlap = $this->overlapWith($other);

        if (! $overlap instanceof Period) {
            return new PeriodCollection($this, $other);
        }

        return $this->subtract($overlap)->add(...$other->subtract($overlap)->all())->sorted();
    }

    /** The same length again, starting where this one ends. */
    public function renew(): self
    {
        $step = ltrim($this->precision->interval(), '+');
        $start = $this->includedEnd->modify('+' . $step);

        // length() counts both endpoints, so a 31-day period advances 30 steps
        // from its new start. Advancing 31 would renew into 32 days.
        return new self(
            $start,
            $start->modify(sprintf('+%d %s', $this->length() - 1, strtolower($this->precision->name))),
            $this->precision,
            $this->boundaries,
        );
    }

    public function isStartIncluded(): bool
    {
        return $this->boundaries->startIncluded();
    }

    public function isStartExcluded(): bool
    {
        return $this->boundaries->startExcluded();
    }

    public function isEndIncluded(): bool
    {
        return $this->boundaries->endIncluded();
    }

    public function isEndExcluded(): bool
    {
        return $this->boundaries->endExcluded();
    }

    public function includedStart(): DateTimeImmutable
    {
        return $this->includedStart;
    }

    public function includedEnd(): DateTimeImmutable
    {
        return $this->includedEnd;
    }

    /** The first moment after this period, at its own precision. */
    public function ceilingEnd(): DateTimeImmutable
    {
        return $this->includedEnd->modify($this->precision->interval());
    }

    /** How many whole steps of this period's precision it spans, endpoints included. */
    public function length(): int
    {
        $length = 0;
        $cursor = $this->includedStart;

        while ($cursor <= $this->includedEnd) {
            $length++;
            $cursor = $cursor->modify($this->precision->interval());
        }

        return $length;
    }

    public function duration(): PeriodDuration
    {
        return new PeriodDuration(
            $this->includedStart->diff($this->includedEnd),
            $this->length(),
            $this->precision,
        );
    }

    /**
     * Every moment in the period, one step of its precision apart.
     *
     * @return list<DateTimeImmutable>
     */
    public function moments(): array
    {
        $moments = [];
        $cursor = $this->includedStart;

        while ($cursor <= $this->includedEnd) {
            $moments[] = $cursor;
            $cursor = $cursor->modify($this->precision->interval());
        }

        return $moments;
    }

    /** @return array{start: string, end: string, precision: string, boundaries: string} */
    public function toArray(): array
    {
        return [
            'start' => $this->start->format(DateTimeInterface::ATOM),
            'end' => $this->end->format(DateTimeInterface::ATOM),
            'precision' => strtolower($this->precision->name),
            'boundaries' => $this->boundaries->name,
        ];
    }

    /** @return array{start: string, end: string, precision: string, boundaries: string} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function __toString(): string
    {
        $format = $this->precision === Precision::Day ? 'Y-m-d' : 'Y-m-d H:i:s';

        return '[' . $this->includedStart->format($format) . ', ' . $this->includedEnd->format($format) . ']';
    }

    /** The overlap with one other period, or null. */
    private function overlapWith(self $other): ?self
    {
        $this->assertSamePrecision($other);

        if (! $this->overlapsWith($other)) {
            return null;
        }

        return new self(
            max($this->includedStart, $other->includedStart),
            min($this->includedEnd, $other->includedEnd),
            $this->precision,
        );
    }

    private function round(DateTimeInterface $date): DateTimeImmutable
    {
        return $this->precision->round($date);
    }

    /**
     * @throws InvalidPeriod
     */
    private function assertSamePrecision(self $other): void
    {
        if ($this->precision !== $other->precision) {
            throw InvalidPeriod::precisionMismatch($this->precision, $other->precision);
        }
    }

    /**
     * @throws InvalidPeriod
     */
    private static function moment(DateTimeInterface|string $value): DateTimeImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception $exception) {
            throw InvalidPeriod::unparsable($value, $exception);
        }
    }
}
