<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Timezone\Resolver;

use Simtabi\Laranail\Chrono\Core\Contracts\TimezoneResolver;

/**
 * An exact IANA identifier, matched case-insensitively.
 *
 * Membership in `listIdentifiers()` is the test, never "the `DateTimeZone` constructor did not
 * throw". Verified: `CEST`, `+03:00`, `GMT+3` and `Z` all construct successfully and appear in no
 * identifier list at all, so constructing would accept nonsense as a region zone.
 */
final readonly class IdentifierResolver implements TimezoneResolver
{
    public function key(): string
    {
        return 'identifier';
    }

    public function resolve(mixed $input, ResolutionContext $context): ?Resolution
    {
        if (! is_string($input) || $input === '') {
            return null;
        }

        return $this->match($input, $context->repository->identifiers())
            ?? $this->match($input, $this->selfCanonical($context));
    }

    /**
     * Identifiers that are not canonical but are also not aliases, so they stand for themselves.
     *
     * `EST`, `CET`, `MST7MDT` and the `Etc/*` zones live only in the backward-compatible list yet
     * have no canonical target — they carry their own rules or are fixed offsets. They are matched
     * in a second pass, *after* the canonical list, so a genuine alias such as `Asia/Calcutta` falls
     * through to `AliasResolver` instead of being returned unchanged. Getting this order wrong is
     * exactly how `Asia/Calcutta` and `Asia/Kolkata` end up in a database as two different zones.
     *
     * @return list<string>
     */
    private function selfCanonical(ResolutionContext $context): array
    {
        $aliases = $context->repository->aliases();

        return array_values(array_filter(
            array_diff(
                $context->repository->identifiers(includeDeprecated: true),
                $context->repository->identifiers(),
            ),
            static fn (string $identifier): bool => ! isset($aliases[$identifier]),
        ));
    }

    /** @param list<string> $haystack */
    private function match(string $input, array $haystack): ?Resolution
    {
        if (in_array($input, $haystack, true)) {
            return new Resolution($input, $this->key());
        }

        $folded = strtolower(str_replace(' ', '_', trim($input)));

        foreach ($haystack as $identifier) {
            if (strtolower($identifier) === $folded) {
                return new Resolution($identifier, $this->key());
            }
        }

        return null;
    }
}
