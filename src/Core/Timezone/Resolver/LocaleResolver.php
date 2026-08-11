<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Timezone\Resolver;

use Simtabi\Laranail\Chrono\Core\Contracts\TimezoneResolver;

/** A locale such as `en_KE` or `sw-KE`: take the region subtag and defer to the country strategy. */
final readonly class LocaleResolver implements TimezoneResolver
{
    public function __construct(private CountryResolver $countries = new CountryResolver) {}

    public function key(): string
    {
        return 'locale';
    }

    public function resolve(mixed $input, ResolutionContext $context): ?Resolution
    {
        if (! is_string($input) || preg_match('/^[a-z]{2,3}[_-]([A-Za-z]{2})$/', $input, $matches) !== 1) {
            return null;
        }

        $resolution = $this->countries->resolve($matches[1], $context);

        return $resolution instanceof Resolution
            ? new Resolution($resolution->identifier, $this->key(), $resolution->confidence, $resolution->alternatives)
            : null;
    }
}
