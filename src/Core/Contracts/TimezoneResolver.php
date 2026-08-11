<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Contracts;

use Simtabi\Laranail\Chrono\Core\Timezone\Resolver\Resolution;
use Simtabi\Laranail\Chrono\Core\Timezone\Resolver\ResolutionContext;

/**
 * One way of turning arbitrary input into a canonical identifier.
 *
 * Each strategy is its own class so adding a new input format is a new file plus a config line,
 * never an edit to a growing `match` block. Returning `null` means "not mine" and passes the input
 * along the chain.
 */
interface TimezoneResolver
{
    public function resolve(mixed $input, ResolutionContext $context): ?Resolution;

    /** Stable key used to order and toggle strategies from configuration. */
    public function key(): string;
}
