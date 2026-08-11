<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Timezone\Resolver;

use Simtabi\Laranail\Chrono\Core\Contracts\TimezoneResolver;
use Simtabi\Laranail\Chrono\Core\Timezone\Support\AliasMap;

/**
 * A deprecated identifier, rewritten to its canonical target.
 *
 * This is the strategy that stops `Asia/Calcutta` and `Asia/Kolkata` ending up in a database as two
 * different zones. Neither PHP nor ICU can do it: `DateTimeZone::getName()` returns the alias
 * unchanged, and `IntlTimeZone::getCanonicalID('Asia/Calcutta')` returns `Asia/Calcutta`.
 */
final readonly class AliasResolver implements TimezoneResolver
{
    /** @param array<string, string> $overrides */
    public function __construct(private array $overrides = []) {}

    public function key(): string
    {
        return 'alias';
    }

    public function resolve(mixed $input, ResolutionContext $context): ?Resolution
    {
        if (! is_string($input) || $input === '') {
            return null;
        }

        $canonical = $this->overrides[$input] ?? AliasMap::canonical($input);

        return $canonical === null ? null : new Resolution($canonical, $this->key());
    }
}
