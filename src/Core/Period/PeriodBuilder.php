<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Period;

use DateTimeInterface;
use Simtabi\Laranail\Chrono\Core\Exception\InvalidPeriod;

/**
 * The fluent way into a {@see Period}.
 *
 *     Chrono::period()->from('2026-01-01')->to('2026-03-31')->months()->build();
 *     Chrono::period()->from($start)->lasting(7)->days()->excludingEnd()->build();
 *
 * The builder exists because a period has four things to decide and only two of
 * them are obvious at the call site. `new Period($a, $b, Precision::Day,
 * Boundaries::ExcludeEnd)` reads as two dates and two pieces of trivia; naming
 * them turns the trivia into the sentence it actually is.
 *
 * Mutable while building, so it can be filled in over several lines, and
 * {@see build()} hands back an immutable period.
 */
final class PeriodBuilder
{
    private DateTimeInterface|string|null $start = null;

    private DateTimeInterface|string|null $end = null;

    private ?int $length = null;

    private Precision $precision = Precision::Day;

    private Boundaries $boundaries = Boundaries::IncludeAll;

    public function from(DateTimeInterface|string $start): self
    {
        $this->start = $start;

        return $this;
    }

    public function to(DateTimeInterface|string $end): self
    {
        $this->end = $end;

        return $this;
    }

    /** How many steps of the chosen precision the period runs for. */
    public function lasting(int $steps): self
    {
        $this->length = $steps;

        return $this;
    }

    public function precision(Precision $precision): self
    {
        $this->precision = $precision;

        return $this;
    }

    public function years(): self
    {
        return $this->precision(Precision::Year);
    }

    public function months(): self
    {
        return $this->precision(Precision::Month);
    }

    public function days(): self
    {
        return $this->precision(Precision::Day);
    }

    public function hours(): self
    {
        return $this->precision(Precision::Hour);
    }

    public function minutes(): self
    {
        return $this->precision(Precision::Minute);
    }

    public function seconds(): self
    {
        return $this->precision(Precision::Second);
    }

    public function boundaries(Boundaries $boundaries): self
    {
        $this->boundaries = $boundaries;

        return $this;
    }

    public function includingAll(): self
    {
        return $this->boundaries(Boundaries::IncludeAll);
    }

    public function excludingStart(): self
    {
        return $this->boundaries(Boundaries::ExcludeStart);
    }

    public function excludingEnd(): self
    {
        return $this->boundaries(Boundaries::ExcludeEnd);
    }

    public function excludingAll(): self
    {
        return $this->boundaries(Boundaries::ExcludeAll);
    }

    /**
     * @throws InvalidPeriod when there is no start, or neither an end nor a length
     */
    public function build(): Period
    {
        if ($this->start === null) {
            throw InvalidPeriod::unparsable('a period needs a start; call from() first');
        }

        if ($this->end === null && $this->length === null) {
            throw InvalidPeriod::unparsable('a period needs an end; call to() or lasting()');
        }

        if ($this->end !== null) {
            return Period::make($this->start, $this->end, $this->precision, $this->boundaries);
        }

        $period = Period::make($this->start, $this->start, $this->precision, Boundaries::IncludeAll);

        // lasting(1) is the start step itself, so a seven-day period advances six.
        $end = $period->includedStart()->modify(
            sprintf('+%d %s', max(0, $this->length - 1), strtolower($this->precision->name)),
        );

        return Period::make($period->includedStart(), $end, $this->precision, $this->boundaries);
    }

    /** A collection seeded with the period this builder describes. */
    public function collect(): PeriodCollection
    {
        return new PeriodCollection($this->build());
    }
}
