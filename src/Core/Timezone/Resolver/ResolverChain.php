<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Timezone\Resolver;

use BackedEnum;
use Stringable;
use Simtabi\Laranail\Chrono\Core\Timezone\Value\Timezone;
use Simtabi\Laranail\Chrono\Core\Contracts\TimezoneResolver;

/**
 * Tries each strategy in turn and takes the first confident answer.
 *
 * Order is load-bearing, not cosmetic. `identifier` must come before `abbreviation` because `EST` is
 * simultaneously a real identifier in the backward-compatible list and an abbreviation for 41 zones;
 * checking the list first means the exact match wins. `alias` follows `identifier` so a deprecated
 * name is recognised before anything tries to interpret it as a place.
 *
 * A strategy returning a resolution with zero confidence has found candidates but refuses to choose
 * between them — that is reported rather than silently taken.
 */
final readonly class ResolverChain implements TimezoneResolver
{
    /** @var list<TimezoneResolver> */
    private array $resolvers;

    public function __construct(TimezoneResolver ...$resolvers)
    {
        $this->resolvers = array_values($resolvers);
    }

    /** The default order. Anything beyond `alias` is a heuristic and is ordered by how exact it is. */
    public static function default(
        AliasResolver $aliases = new AliasResolver,
        CountryResolver $countries = new CountryResolver,
    ): self {
        return new self(
            new InstanceResolver,
            new IdentifierResolver,
            $aliases,
            new OffsetResolver,
            new WindowsResolver,
            $countries,
            new LocaleResolver($countries),
            new AbbreviationResolver,
            new CityResolver,
        );
    }

    public function key(): string
    {
        return 'chain';
    }

    public function resolve(mixed $input, ResolutionContext $context): ?Resolution
    {
        $input = $this->unwrap($input);
        $best = null;

        foreach ($this->resolvers as $resolver) {
            $resolution = $resolver->resolve($input, $context);

            if ($resolution === null) {
                continue;
            }

            if ($resolution->confidence >= 1.0) {
                return $resolution;
            }

            // Keep the strongest partial answer in case nothing certain turns up.
            if ($best === null || $resolution->confidence > $best->confidence) {
                $best = $resolution;
            }
        }

        if ($best === null) {
            return null;
        }

        // A zero-confidence result means "ambiguous, and I will not choose".
        return $context->strict && $best->confidence <= 0.0 ? null : $best;
    }

    /** @return list<string> */
    public function keys(): array
    {
        return array_map(static fn (TimezoneResolver $r): string => $r->key(), $this->resolvers);
    }

    /** Narrow the chain to a configured subset, preserving the configured order. */
    public function only(string ...$keys): self
    {
        $wanted = array_values($keys);

        $filtered = [];

        foreach ($wanted as $key) {
            foreach ($this->resolvers as $resolver) {
                if ($resolver->key() === $key) {
                    $filtered[] = $resolver;
                }
            }
        }

        return new self(...$filtered);
    }

    /**
     * Reduce a wrapper to the string inside it, then let the chain judge that string.
     *
     * Deliberately an unwrap rather than a resolver of its own. A strategy that answered "this is a
     * `Tz` case, so it is a valid zone" would short-circuit before anything validated it, and the
     * same shortcut applied to `TimezoneAbbreviation::CST` would assert that `CST` names one zone
     * when it names sixty-two. Unwrapping keeps every value on the same footing: `Tz` cases pass the
     * identifier check because they *are* identifiers, and abbreviations still have to earn their
     * answer from the abbreviation strategy, which the configuration can refuse to enable.
     */
    private function unwrap(mixed $input): mixed
    {
        if ($input instanceof BackedEnum) {
            return $input->value;
        }

        // `Timezone` is excluded even though it is Stringable: it carries a zone rather than spells
        // one, and the instance strategy reads it exactly. Rendering it to a string first would send
        // a deprecated identifier back through validation for no reason.
        if ($input instanceof Stringable && ! $input instanceof Timezone) {
            return (string) $input;
        }

        return $input;
    }
}
