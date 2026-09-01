<?php

declare(strict_types=1);

use Psr\SimpleCache\CacheInterface;
use Simtabi\Laranail\Chrono\Core\Enums\Region;
use Simtabi\Laranail\Chrono\Core\Timezone\Repository\CachedTimezoneRepository;
use Simtabi\Laranail\Chrono\Core\Timezone\Repository\PhpTimezoneRepository;

beforeEach(function (): void {
    $this->repository = new PhpTimezoneRepository;
});

it('separates canonical identifiers from the backward-compatible list', function (): void {
    $canonical = $this->repository->identifiers();
    $withDeprecated = $this->repository->identifiers(includeDeprecated: true);

    expect(array_diff($canonical, DateTimeZone::listIdentifiers(DateTimeZone::ALL)))->toBe([])
        ->and(array_diff($withDeprecated, DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC)))->toBe([])
        ->and(array_diff($canonical, $withDeprecated))->toBe([])
        ->and(count($withDeprecated))->toBeGreaterThan(count($canonical));
});

/**
 * Deliberately a subset of `listIdentifiers()` rather than equal to it. On a system-tzdata build —
 * the official Docker images, Debian, Ubuntu — PHP reports whatever is in `/usr/share/zoneinfo`,
 * so the raw list carries `tzdata.zi` and `leapseconds` alongside the zones. The first would reach a
 * picker as if it were a place and the second throws when constructed.
 */
it('never reports a file as a zone', function (): void {
    $unbuildable = [];

    foreach ($this->repository->identifiers(includeDeprecated: true) as $identifier) {
        try {
            // The real guarantee: everything it offers can actually be built.
            new DateTimeZone($identifier);
        } catch (Throwable) {
            $unbuildable[] = $identifier;
        }
    }

    $withDots = array_values(array_filter(
        $this->repository->identifiers(includeDeprecated: true),
        static fn (string $identifier): bool => str_contains($identifier, '.'),
    ));

    expect($unbuildable)->toBe([])->and($withDots)->toBe([]);
});

/**
 * `getLocation()` returns a `country_code` that php.net does not document but that is populated for
 * every canonical zone except UTC. Building the index from it is one pass; the obvious alternative
 * is sweeping 249 country codes through `listIdentifiers(PER_COUNTRY, …)`.
 */
it('maps zones to countries from the undocumented location field', function (): void {
    expect($this->repository->countryOf('Africa/Nairobi'))->toBe('KE')
        ->and($this->repository->countryOf('America/New_York'))->toBe('US')
        ->and($this->repository->countryOf('UTC'))->toBeNull()
        ->and($this->repository->forCountry('KE'))->toBe(['Africa/Nairobi'])
        ->and($this->repository->countryIndex())->toHaveKey('KE');
});

it('finds Etc zones, which live only in the backward-compatible list', function (): void {
    expect($this->repository->forRegion(Region::Etc))->not->toBeEmpty()
        ->and($this->repository->forRegion(Region::Africa))->toContain('Africa/Nairobi');
});

it('reports PHP tzdata version, not ICU\'s', function (): void {
    // The two are shipped independently and drift badly — one machine ran PHP 2025.3 against
    // ICU 2019a. Anything that decides behaviour must key on PHP's.
    expect($this->repository->version())->toBe(timezone_version_get());
});

it('produces a stable fingerprint', function (): void {
    expect($this->repository->fingerprint())
        ->toHaveLength(12)
        ->toBe((new PhpTimezoneRepository)->fingerprint());
});

/**
 * A cache is an optimisation, and one that can take a request down is not one. The store belongs to
 * the application: it can be a database table nobody migrated, a Redis that is down, or a driver
 * misconfigured in one environment. None of those are reasons for `Timezones::of()` to throw —
 * reading the tz database directly is always correct, only slower.
 *
 * Found by running the suite in the development container, where the default store happened to be
 * one with no table behind it.
 */
it('answers from the database when the cache throws', function (): void {
    $broken = new class implements CacheInterface
    {
        public function get(string $key, mixed $default = null): mixed
        {
            throw new RuntimeException('no such table: cache');
        }

        public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
        {
            throw new RuntimeException('no such table: cache');
        }

        public function delete(string $key): bool
        {
            throw new RuntimeException('no such table: cache');
        }

        public function clear(): bool
        {
            throw new RuntimeException('no such table: cache');
        }

        public function getMultiple(iterable $keys, mixed $default = null): iterable
        {
            throw new RuntimeException('no such table: cache');
        }

        public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
        {
            throw new RuntimeException('no such table: cache');
        }

        public function deleteMultiple(iterable $keys): bool
        {
            throw new RuntimeException('no such table: cache');
        }

        public function has(string $key): bool
        {
            throw new RuntimeException('no such table: cache');
        }
    };

    $repository = new CachedTimezoneRepository(new PhpTimezoneRepository, $broken);

    expect($repository->abbreviations())->not->toBeEmpty()
        ->and($repository->countryIndex())->toHaveKey('KE')
        ->and($repository->countryOf('Africa/Nairobi'))->toBe('KE');

    // Also best effort: an unreachable cache has nothing to clear.
    $repository->flush();

    expect($repository->version())->toBe(timezone_version_get());
});
