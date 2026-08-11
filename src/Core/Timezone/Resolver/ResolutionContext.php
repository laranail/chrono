<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Timezone\Resolver;

use Simtabi\Laranail\Chrono\Core\Contracts\TimezoneRepository;

/**
 * Everything a strategy needs, passed per call rather than injected per strategy.
 *
 * That keeps the strategies stateless and lets a caller vary the country bias for one lookup
 * without rebuilding the chain.
 */
final readonly class ResolutionContext
{
    /** @param list<string> $preferredCountries */
    public function __construct(
        public TimezoneRepository $repository,
        public array $preferredCountries = [],
        public bool $strict = true,
        public bool $allowAbbreviations = false,
    ) {}

    /** @param list<string> $countries */
    public function preferring(array $countries): self
    {
        return clone ($this, ['preferredCountries' => array_map(strtoupper(...), $countries)]);
    }
}
