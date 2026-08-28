<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Period;

use Closure;
use Countable;
use Stringable;
use Traversable;
use ArrayIterator;
use JsonSerializable;
use IteratorAggregate;
use Simtabi\Laranail\Chrono\Core\Exception\InvalidPeriod;

/**
 * Several periods, operated on together.
 *
 * The collection is where the interesting questions live: when is everybody
 * free, what is left of the week once these bookings are removed, where are the
 * holes. Every operation returns a new collection, so a chain never disturbs
 * what it read.
 *
 * @implements IteratorAggregate<int, Period>
 */
final readonly class PeriodCollection implements Countable, IteratorAggregate, JsonSerializable, Stringable
{
    /** @var list<Period> */
    private array $periods;

    public function __construct(Period ...$periods)
    {
        $this->periods = array_values($periods);
    }

    public function __toString(): string
    {
        return implode(', ', array_map(static fn (Period $p): string => (string) $p, $this->periods));
    }

    /** @return list<Period> */
    public function all(): array
    {
        return $this->periods;
    }

    public function add(Period ...$periods): self
    {
        return new self(...$this->periods, ...$periods);
    }

    /** The span every collection given has in common. */
    public function overlapAll(self ...$others): self
    {
        $overlap = $this;

        foreach ($others as $other) {
            $overlap = $overlap->overlapWith($other);
        }

        return $overlap;
    }

    /** What is left once every given period is removed from every period here. */
    public function subtract(self|Period ...$others): self
    {
        $subtrahends = [];

        foreach ($others as $other) {
            if ($other instanceof self) {
                $subtrahends = [...$subtrahends, ...$other->all()];

                continue;
            }

            $subtrahends[] = $other;
        }

        if ($subtrahends === []) {
            return $this;
        }

        $result = new self;

        foreach ($this->periods as $period) {
            $result = $result->add(...$period->subtract(...$subtrahends)->all());
        }

        return $result;
    }

    /** The single period spanning everything here, gaps included. */
    public function boundaries(): ?Period
    {
        if ($this->periods === []) {
            return null;
        }

        $start = null;
        $end = null;

        foreach ($this->periods as $period) {
            $start = $start === null || $period->includedStart() < $start ? $period->includedStart() : $start;
            $end = $end === null || $period->includedEnd() > $end ? $period->includedEnd() : $end;
        }

        return new Period($start, $end, $this->periods[0]->precision);
    }

    /** The spans between the periods here, in order. */
    public function gaps(): self
    {
        $boundaries = $this->boundaries();

        if (! $boundaries instanceof Period) {
            return new self;
        }

        return $boundaries->subtract(...$this->periods);
    }

    /** Each period here, clipped to the given span. */
    public function intersect(Period $intersection): self
    {
        $result = new self;

        foreach ($this->periods as $period) {
            $overlap = $intersection->overlap($period);

            if ($overlap instanceof Period) {
                $result = $result->add($overlap);
            }
        }

        return $result;
    }

    /** Overlapping and touching periods merged into single spans. */
    public function union(): self
    {
        if ($this->periods === []) {
            return new self;
        }

        $sorted = $this->sorted()->all();
        $merged = [];
        $current = $sorted[0];

        foreach (array_slice($sorted, 1) as $period) {
            if ($current->overlapsWith($period) || $current->touchesWith($period)) {
                $current = new Period(
                    min($current->includedStart(), $period->includedStart()),
                    max($current->includedEnd(), $period->includedEnd()),
                    $current->precision,
                );

                continue;
            }

            $merged[] = $current;
            $current = $period;
        }

        $merged[] = $current;

        return new self(...$merged);
    }

    /** In start order, earliest first. */
    public function sorted(): self
    {
        $periods = $this->periods;

        usort($periods, fn (Period $a, Period $b): int => $a->includedStart() <=> $b->includedStart());

        return new self(...$periods);
    }

    /** @param Closure(Period): Period $closure */
    public function map(Closure $closure): self
    {
        return new self(...array_map($closure, $this->periods));
    }

    /** @param Closure(Period): bool $closure */
    public function filter(Closure $closure): self
    {
        return new self(...array_values(array_filter($this->periods, $closure)));
    }

    /** @param Closure(mixed, Period): mixed $closure */
    public function reduce(Closure $closure, mixed $initial = null): mixed
    {
        return array_reduce($this->periods, $closure, $initial);
    }

    public function first(): ?Period
    {
        return $this->periods[0] ?? null;
    }

    public function last(): ?Period
    {
        return $this->periods === [] ? null : $this->periods[count($this->periods) - 1];
    }

    public function isEmpty(): bool
    {
        return $this->periods === [];
    }

    public function isNotEmpty(): bool
    {
        return ! $this->isEmpty();
    }

    public function count(): int
    {
        return count($this->periods);
    }

    /**
     * The total length of every period here, in steps of their precision.
     *
     * @throws InvalidPeriod when the collection mixes precisions
     */
    public function length(): int
    {
        $precision = null;
        $length = 0;

        foreach ($this->periods as $period) {
            if ($precision !== null && $period->precision !== $precision) {
                throw InvalidPeriod::precisionMismatch($precision, $period->precision);
            }

            $precision = $period->precision;
            $length += $period->length();
        }

        return $length;
    }

    /** @return Traversable<int, Period> */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->periods);
    }

    /** @return list<array{start: string, end: string, precision: string, boundaries: string}> */
    public function toArray(): array
    {
        return array_map(static fn (Period $period): array => $period->toArray(), $this->periods);
    }

    /** @return list<array{start: string, end: string, precision: string, boundaries: string}> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /** The overlap between this collection and one other. */
    private function overlapWith(self $other): self
    {
        $result = new self;

        foreach ($this->periods as $period) {
            foreach ($other->all() as $otherPeriod) {
                $overlap = $period->overlap($otherPeriod);

                if ($overlap !== null) {
                    $result = $result->add($overlap);
                }
            }
        }

        return $result;
    }
}
