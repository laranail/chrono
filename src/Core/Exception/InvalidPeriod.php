<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Exception;

use DateTimeInterface;
use Simtabi\Laranail\Chrono\Core\Period\Precision;
use Throwable;

final class InvalidPeriod extends ChronoExceptionBase
{
    public static function endBeforeStart(DateTimeInterface $start, DateTimeInterface $end): self
    {
        $exception = new self(sprintf(
            'A period cannot end before it starts: %s comes after %s.',
            $start->format(DateTimeInterface::ATOM),
            $end->format(DateTimeInterface::ATOM),
        ));
        $exception->context = [
            'start' => $start->format(DateTimeInterface::ATOM),
            'end' => $end->format(DateTimeInterface::ATOM),
        ];

        return $exception;
    }

    /**
     * Two periods measuring different units cannot be compared.
     *
     * Rounding one to the other would answer a question nobody asked: whether a
     * period of months overlaps a period of seconds depends entirely on which
     * way the rounding went, so the comparison is refused instead.
     */
    public static function precisionMismatch(Precision $left, Precision $right): self
    {
        $exception = new self(sprintf(
            'Periods measured in %s and %s cannot be compared. Rebuild one at the '.
            'other\'s precision first, with Period::make($start, $end, Precision::%s).',
            strtolower($left->name),
            strtolower($right->name),
            $left->name,
        ));
        $exception->context = ['left' => $left->name, 'right' => $right->name];

        return $exception;
    }

    public static function unparsable(string $value, ?Throwable $previous = null): self
    {
        $exception = new self(sprintf('Could not read "%s" as a date or time.', $value), 0, $previous);
        $exception->context = ['value' => $value];

        return $exception;
    }

    public static function emptyCollection(string $operation): self
    {
        $exception = new self(sprintf(
            'Cannot take %s of an empty period collection.',
            $operation,
        ));
        $exception->context = ['operation' => $operation];

        return $exception;
    }
}
