<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Timezone\Resolver;

use DateTimeInterface;
use DateTimeZone;
use IntlTimeZone;
use Simtabi\Laranail\Chrono\Core\Contracts\TimezoneResolver;
use Simtabi\Laranail\Chrono\Core\Timezone\Value\Timezone;

/** Objects that already carry a zone. First in the chain because it is the cheapest and exact. */
final readonly class InstanceResolver implements TimezoneResolver
{
    public function key(): string
    {
        return 'instance';
    }

    public function resolve(mixed $input, ResolutionContext $context): ?Resolution
    {
        $identifier = match (true) {
            $input instanceof Timezone => $input->identifier,
            $input instanceof DateTimeZone => $input->getName(),
            $input instanceof DateTimeInterface => $input->getTimezone()->getName(),
            $input instanceof IntlTimeZone => $input->getID(),
            default => null,
        };

        return is_string($identifier) && $identifier !== ''
            ? new Resolution($identifier, $this->key())
            : null;
    }
}
