<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Contracts;

use Psr\Clock\ClockInterface;

/**
 * Marker for this package's clock, so the container can bind ours without hijacking a host
 * application's own PSR-20 binding. Structurally identical to `Psr\Clock\ClockInterface`.
 */
interface Clock extends ClockInterface {}
