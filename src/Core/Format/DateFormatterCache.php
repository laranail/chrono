<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Format;

use IntlDatePatternGenerator;

/**
 * Memoises skeleton-to-pattern resolution.
 *
 * Constructing an `IntlDatePatternGenerator` builds ICU's whole pattern table for a locale, which is
 * far more expensive than the `getBestPattern()` call that follows. Since a request usually formats
 * many dates in one or two locales, holding the generators is worth it — and unlike the timezone
 * caches this needs no fingerprint, because a skeleton's resolution changes only with the ICU build
 * itself, which cannot change inside a process.
 */
final class DateFormatterCache
{
    /** @var array<string, IntlDatePatternGenerator> */
    private array $generators = [];

    /** @var array<string, string> */
    private array $patterns = [];

    public function patternFor(string $locale, string $skeleton): string
    {
        $key = $locale.'|'.$skeleton;

        if (isset($this->patterns[$key])) {
            return $this->patterns[$key];
        }

        $generator = $this->generators[$locale] ??= new IntlDatePatternGenerator($locale);

        $pattern = $generator->getBestPattern($skeleton);

        // ICU returns false for a malformed skeleton; fall back to the skeleton itself so the
        // caller sees something recognisable rather than an empty string.
        return $this->patterns[$key] = $pattern === false ? $skeleton : $pattern;
    }
}
