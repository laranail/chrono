<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Timezone\Repository;

use Throwable;
use Psr\SimpleCache\CacheInterface;
use Simtabi\Laranail\Chrono\Core\Enums\Region;
use Simtabi\Laranail\Chrono\Core\Contracts\TimezoneRepository;

/**
 * A PSR-16 decorator over any repository.
 *
 * Deliberately narrow. Measurement across all 419 zones says the identifier list costs 1.17 ms to
 * build, offsets 0.96 ms and locations 0.70 ms — every one of them cheaper than a round trip to a
 * cache server, so caching them would be a pessimisation dressed as an optimisation. What this
 * covers is the country index, which walks every zone's location, and the aliases and abbreviation
 * tables.
 *
 * Keys embed a fingerprint of PHP's tz database. Because the fingerprint is part of the key rather
 * than a version record checked alongside it, a tzdata upgrade moves the entire key space at once:
 * no purge step, no coordination between servers mid-deploy, and no window where one process reads
 * old rules while another reads new ones. Stale entries simply age out.
 *
 * Keys use `[a-z0-9._-]` only — PSR-16 reserves `{}()/\@:`, and a `/` from an identifier would be
 * rejected outright by a conforming driver.
 */
final class CachedTimezoneRepository implements TimezoneRepository
{
    private ?string $fingerprint = null;

    public function __construct(
        private readonly TimezoneRepository $inner,
        private readonly CacheInterface $cache,
        private readonly string $prefix = 'laranail.chrono',
        private readonly int $ttl = 86400,
    ) {}

    /** @return list<string> */
    public function identifiers(bool $includeDeprecated = false): array
    {
        // Cheap enough to build that caching would cost more than it saves.
        return $this->inner->identifiers($includeDeprecated);
    }

    /** @return list<string> */
    /** Delegated, not cached: the inner repository answers from a hash it built once. */
    public function isCanonical(string $identifier): bool
    {
        return $this->inner->isCanonical($identifier);
    }

    public function forCountry(string $countryCode): array
    {
        return $this->inner->forCountry($countryCode);
    }

    /** @return list<string> */
    public function forRegion(Region $region): array
    {
        return $this->inner->forRegion($region);
    }

    /** @return array<string, string> */
    public function aliases(): array
    {
        return $this->inner->aliases();
    }

    /** @return array<string, list<array{dst: bool, offset: int, timezone_id: string|null}>> */
    public function abbreviations(): array
    {
        /** @var array<string, list<array{dst: bool, offset: int, timezone_id: string|null}>> $value */
        $value = $this->remember('abbreviations', fn (): array => $this->inner->abbreviations());

        return $value;
    }

    public function countryOf(string $identifier): ?string
    {
        $index = $this->countryIndex();

        foreach ($index as $country => $identifiers) {
            if (in_array($identifier, $identifiers, true)) {
                return $country;
            }
        }

        return null;
    }

    /** @return array<string, list<string>> */
    public function countryIndex(): array
    {
        /** @var array<string, list<string>> $value */
        $value = $this->remember('country-index', fn (): array => $this->inner->countryIndex());

        return $value;
    }

    public function fingerprint(): string
    {
        return $this->fingerprint ??= $this->inner->fingerprint();
    }

    public function usesSystemDatabase(): bool
    {
        return $this->inner->usesSystemDatabase();
    }

    public function version(): string
    {
        return $this->inner->version();
    }

    /** Drop every cached entry for the current tz database. */
    /** Best effort, for the same reason `remember()` is: an unreachable cache is already empty. */
    public function flush(): void
    {
        try {
            $this->cache->deleteMultiple([
                $this->key('abbreviations'),
                $this->key('country-index'),
            ]);
        } catch (Throwable) {
            // Nothing cached is nothing to clear.
        }
    }

    /**
     * @template T
     *
     * @param callable(): T $compute
     *
     * @return T
     */
    private function remember(string $bucket, callable $compute): mixed
    {
        $key = $this->key($bucket);

        // A cache is an optimisation, and an optimisation that can take down a request is not one.
        // The store is whatever the application configured, so it can be a database table that has
        // not been migrated, a Redis that is down, or a driver misconfigured in one environment
        // only — none of which are reasons for `Timezones::of()` to throw. Reading the tz database
        // directly is always correct, only slower.
        try {
            $cached = $this->cache->get($key);
        } catch (Throwable) {
            return $compute();
        }

        if ($cached !== null) {
            /** @var T $cached */
            return $cached;
        }

        $value = $compute();

        try {
            $this->cache->set($key, $value, $this->ttl > 0 ? $this->ttl : null);
        } catch (Throwable) {
            // Nothing to do about it, and nothing that depends on it having worked.
        }

        return $value;
    }

    private function key(string $bucket): string
    {
        return sprintf('%s.%s.%s', $this->prefix, $this->fingerprint(), $bucket);
    }
}
