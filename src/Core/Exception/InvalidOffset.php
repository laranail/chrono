<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Exception;

final class InvalidOffset extends ChronoExceptionBase
{
    public static function outOfRange(int $seconds, int $limit): self
    {
        $exception = new self(sprintf(
            'A UTC offset of %d seconds is outside the supported range of +/-%d seconds (+/-%d hours).',
            $seconds,
            $limit,
            intdiv($limit, 3600),
        ));

        $exception->context = ['seconds' => $seconds, 'limit' => $limit];

        return $exception;
    }

    public static function unparsable(string $value): self
    {
        $exception = new self(sprintf('"%s" is not a recognisable UTC offset.', $value));

        $exception->context = ['value' => $value];

        return $exception;
    }
}
