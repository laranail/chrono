<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Timezone\Resolver;

use Simtabi\Laranail\Chrono\Core\Contracts\TimezoneResolver;
use Simtabi\Laranail\Chrono\Core\Timezone\Support\OffsetParser;

/**
 * A UTC offset: `+03:00`, `-0500`, `+3`, `GMT+3`, `UTC-5`, `Z`, or an integer of seconds.
 *
 * Returns a fixed-offset zone, honestly, rather than picking a city that happens to share the offset
 * today. A city's offset is a fact about a date; an offset is not a place. Note the returned zone has
 * no rules — `getTransitions()` on it yields `false`, which the scanner normalises.
 */
final readonly class OffsetResolver implements TimezoneResolver
{
    public function key(): string
    {
        return 'offset';
    }

    public function resolve(mixed $input, ResolutionContext $context): ?Resolution
    {
        $seconds = match (true) {
            is_int($input) => $input,
            is_string($input) => OffsetParser::tryParse($input),
            default => null,
        };

        if ($seconds === null) {
            return null;
        }

        $sign = $seconds < 0 ? '-' : '+';
        $absolute = abs($seconds);

        $identifier = sprintf('%s%02d:%02d', $sign, intdiv($absolute, 3600), intdiv($absolute % 3600, 60));

        return new Resolution($identifier, $this->key());
    }
}
