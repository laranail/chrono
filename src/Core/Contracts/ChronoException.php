<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Contracts;

use Throwable;

/**
 * Every failure this package throws implements this, so a caller can catch the whole package with
 * one clause. `context()` carries the structured detail — the zone, the instant, the candidates —
 * that a log line or an API error body needs and a message string cannot hold.
 */
interface ChronoException extends Throwable
{
    /** @return array<string, mixed> */
    public function context(): array;
}
