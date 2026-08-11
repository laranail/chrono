<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Exception;

use RuntimeException;
use Simtabi\Laranail\Chrono\Core\Contracts\ChronoException;

abstract class ChronoExceptionBase extends RuntimeException implements ChronoException
{
    /** @var array<string, mixed> */
    protected array $context = [];

    /** @return array<string, mixed> */
    public function context(): array
    {
        return $this->context;
    }
}
