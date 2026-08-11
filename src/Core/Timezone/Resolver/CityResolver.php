<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Timezone\Resolver;

use Simtabi\Laranail\Chrono\Core\Contracts\TimezoneResolver;

/** A city name — `Nairobi`, `new york`, `Sao Paulo` — matched against the last path segment. */
final readonly class CityResolver implements TimezoneResolver
{
    public function key(): string
    {
        return 'city';
    }

    public function resolve(mixed $input, ResolutionContext $context): ?Resolution
    {
        if (! is_string($input) || $input === '' || str_contains($input, '/')) {
            return null;
        }

        $needle = $this->fold($input);
        $matches = [];

        foreach ($context->repository->identifiers() as $identifier) {
            $segment = str_contains($identifier, '/')
                ? substr((string) strrchr($identifier, '/'), 1)
                : $identifier;

            if ($this->fold($segment) === $needle) {
                $matches[] = $identifier;
            }
        }

        if ($matches === []) {
            return null;
        }

        if (count($matches) === 1) {
            return new Resolution($matches[0], $this->key(), 0.8);
        }

        foreach ($context->preferredCountries as $country) {
            foreach ($matches as $candidate) {
                if ($context->repository->countryOf($candidate) === $country) {
                    return new Resolution($candidate, $this->key(), 0.7, $matches);
                }
            }
        }

        return new Resolution($matches[0], $this->key(), 0.4, $matches);
    }

    /** Lowercase, strip diacritics, and treat underscores, hyphens and spaces alike. */
    private function fold(string $value): string
    {
        $value = str_replace(['_', '-'], ' ', trim($value));

        $transliterated = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $value);

        return $transliterated === false ? strtolower($value) : $transliterated;
    }
}
