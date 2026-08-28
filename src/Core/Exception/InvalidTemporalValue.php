<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Exception;

final class InvalidTemporalValue extends ChronoExceptionBase
{
    public static function month(int $month): self
    {
        $exception = new self(sprintf('Month %d is out of range; months run 1 to 12.', $month));
        $exception->context = ['month' => $month];

        return $exception;
    }

    public static function day(int $day, int $month, int $year, int $length): self
    {
        $exception = new self(sprintf(
            'Day %d does not exist in month %d of %d, which has %d days.',
            $day,
            $month,
            $year,
            $length,
        ));
        $exception->context = ['day' => $day, 'month' => $month, 'year' => $year, 'length' => $length];

        return $exception;
    }

    public static function time(int $hour, int $minute, int $second): self
    {
        $exception = new self(sprintf(
            '%02d:%02d:%02d is not a valid time of day.',
            $hour,
            $minute,
            $second,
        ));
        $exception->context = ['hour' => $hour, 'minute' => $minute, 'second' => $second];

        return $exception;
    }

    public static function unparsable(string $value, string $expected): self
    {
        $exception = new self(sprintf('"%s" is not in the expected format %s.', $value, $expected));
        $exception->context = ['value' => $value, 'expected' => $expected];

        return $exception;
    }
}
