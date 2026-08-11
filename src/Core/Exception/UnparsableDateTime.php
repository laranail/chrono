<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Exception;

final class UnparsableDateTime extends ChronoExceptionBase
{
    /** @param list<string> $errors */
    public static function for(string $value, ?string $format = null, array $errors = []): self
    {
        $exception = new self(sprintf(
            'Could not parse "%s"%s.%s',
            $value,
            $format === null ? '' : ' using the format "' . $format . '"',
            $errors === [] ? '' : ' ' . implode(' ', $errors),
        ));

        $exception->context = ['value' => $value, 'format' => $format, 'errors' => $errors];

        return $exception;
    }

    public static function conflictingOffset(string $value, string $zone, string $parsedOffset): self
    {
        $exception = new self(sprintf(
            'The value "%s" carries the offset %s, which conflicts with the requested zone %s. '
            . 'PHP would silently ignore the zone; strict parsing refuses instead.',
            $value,
            $parsedOffset,
            $zone,
        ));

        $exception->context = ['value' => $value, 'zone' => $zone, 'offset' => $parsedOffset];

        return $exception;
    }
}
