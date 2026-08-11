<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Timezone\Resolver;

use Simtabi\Laranail\Chrono\Core\Contracts\TimezoneResolver;

/**
 * A timezone abbreviation such as `EAT`, `EST` or `CST`.
 *
 * Off unless `allowAbbreviations` is set, because abbreviations are mostly ambiguous: of the 144 PHP
 * knows, **96 map to more than one zone** — `CST` to 62 and `EST` to 41. With a country bias
 * configured the winner is chosen explicitly; without one, every candidate is returned as an
 * alternative and confidence stays low so a caller can prompt rather than guess.
 */
final readonly class AbbreviationResolver implements TimezoneResolver
{
    public function key(): string
    {
        return 'abbreviation';
    }

    public function resolve(mixed $input, ResolutionContext $context): ?Resolution
    {
        if (! $context->allowAbbreviations || ! is_string($input) || preg_match('/^[A-Za-z]{2,6}$/', $input) !== 1) {
            return null;
        }

        $entries = $context->repository->abbreviations()[strtolower($input)] ?? [];

        $candidates = [];

        foreach ($entries as $entry) {
            $identifier = $entry['timezone_id'] ?? null;

            if (is_string($identifier) && $identifier !== '' && ! in_array($identifier, $candidates, true)) {
                $candidates[] = $identifier;
            }
        }

        if ($candidates === []) {
            return null;
        }

        if (count($candidates) === 1) {
            return new Resolution($candidates[0], $this->key(), 0.9);
        }

        foreach ($context->preferredCountries as $country) {
            foreach ($candidates as $candidate) {
                if ($context->repository->countryOf($candidate) === $country) {
                    return new Resolution($candidate, $this->key(), 0.7, $candidates);
                }
            }
        }

        return new Resolution($candidates[0], $this->key(), 0.2, $candidates);
    }
}
