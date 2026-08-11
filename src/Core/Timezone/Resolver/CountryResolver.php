<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Timezone\Resolver;

use Simtabi\Laranail\Chrono\Core\Contracts\TimezoneResolver;

/**
 * An ISO-3166 alpha-2 country code.
 *
 * A single-zone country resolves exactly. A multi-zone country deliberately does **not** guess: the
 * United States has 29 zones and picking one for a user is a bug waiting to be filed. In strict mode
 * it returns the alternatives and no answer; in lenient mode it takes the first with low confidence.
 */
final readonly class CountryResolver implements TimezoneResolver
{
    /** @param array<string, string> $defaults country code => chosen identifier */
    public function __construct(private array $defaults = []) {}

    public function key(): string
    {
        return 'country';
    }

    public function resolve(mixed $input, ResolutionContext $context): ?Resolution
    {
        if (! is_string($input) || preg_match('/^[A-Za-z]{2}$/', $input) !== 1) {
            return null;
        }

        $code = strtoupper($input);
        $zones = $context->repository->forCountry($code);

        if ($zones === []) {
            return null;
        }

        if (isset($this->defaults[$code])) {
            return new Resolution($this->defaults[$code], $this->key(), 1.0, $zones);
        }

        if (count($zones) === 1) {
            return new Resolution($zones[0], $this->key(), 0.9);
        }

        return $context->strict
            ? new Resolution($zones[0], $this->key(), 0.0, $zones)
            : new Resolution($zones[0], $this->key(), 0.3, $zones);
    }
}
