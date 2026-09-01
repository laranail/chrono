<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Exception;

final class TimezoneNotFound extends ChronoExceptionBase
{
    public static function forQuery(): self
    {
        return new self('No timezone matched the query.');
    }

    public static function forInput(mixed $input): self
    {
        $exception = new self(sprintf(
            'Could not resolve %s to a timezone.',
            is_scalar($input) ? '"'.$input.'"' : 'the given '.get_debug_type($input),
        ));

        $exception->context = ['input' => is_scalar($input) ? $input : get_debug_type($input)];

        return $exception;
    }
}
