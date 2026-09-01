<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Timezone\Repository;

use DateTimeZone;
use Exception;
use Simtabi\Laranail\Chrono\Core\Contracts\TimezoneRepository;
use Simtabi\Laranail\Chrono\Core\Enums\Region;
use Simtabi\Laranail\Chrono\Core\Timezone\Support\AliasMap;

/**
 * The default repository, reading PHP's bundled tz database directly.
 *
 * Everything here is memoised per instance rather than cached externally, because measurement says
 * external caching would cost more than it saves. Across all 419 zones: building every
 * `DateTimeZone` takes 1.17 ms, `getOffset()` 0.96 ms, `getLocation()` 0.70 ms and
 * `listAbbreviations()` 0.41 ms — all cheaper than a round trip to Redis. Only
 * `getTransitions()` is genuinely expensive at 22.8 ms for the full set, and that is not read here.
 */
final class PhpTimezoneRepository implements TimezoneRepository
{
    /** @var list<string>|null */
    private ?array $canonical = null;

    /** @var list<string>|null */
    private ?array $withDeprecated = null;

    /** @var array<string, true>|null */
    private ?array $canonicalIndex = null;

    /** @var array<string, list<string>>|null */
    private ?array $countryIndex = null;

    /** @var array<string, string>|null */
    private ?array $zoneToCountry = null;

    private ?string $fingerprint = null;

    /** @return list<string> */
    public function identifiers(bool $includeDeprecated = false): array
    {
        if ($includeDeprecated) {
            return $this->withDeprecated ??= $this->onlyZones(
                DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC),
            );
        }

        return $this->canonical ??= $this->onlyZones(DateTimeZone::listIdentifiers(DateTimeZone::ALL));
    }

    /**
     * Whether PHP is reading the operating system's tz database rather than its own bundled copy.
     *
     * Built `--with-system-tzdata` — which is how the official Docker images, Debian and Ubuntu all
     * ship PHP — `timezone_version_get()` returns the literal string `0.system` instead of a release
     * like `2026.1`. Nothing can be compared against that, so anything asserting a minimum version,
     * or checking generated data byte for byte, has to know the ground has moved.
     *
     * The data itself is fine, and often fresher than the bundled copy. It is the *metadata* that is
     * missing.
     */
    public function usesSystemDatabase(): bool
    {
        return ! str_contains($this->version(), '.') || str_starts_with($this->version(), '0.');
    }

    public function isCanonical(string $identifier): bool
    {
        $this->canonicalIndex ??= array_fill_keys($this->identifiers(), true);

        return isset($this->canonicalIndex[$identifier]);
    }

    /** @return list<string> */
    public function forCountry(string $countryCode): array
    {
        $code = strtoupper($countryCode);
        $identifiers = DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, $code);

        return $identifiers === false ? [] : $identifiers;
    }

    /** @return list<string> */
    public function forRegion(Region $region): array
    {
        // Etc/* zones live only in ALL_WITH_BC, so the group mask alone would return nothing.
        if ($region === Region::Etc) {
            return array_values(array_filter(
                $this->identifiers(includeDeprecated: true),
                static fn (string $identifier): bool => str_starts_with($identifier, 'Etc/'),
            ));
        }

        $identifiers = DateTimeZone::listIdentifiers($region->mask());

        if ($region === Region::Utc) {
            return array_values(array_filter(
                $identifiers,
                static fn (string $identifier): bool => ! str_starts_with($identifier, 'Etc/'),
            ));
        }

        return $identifiers;
    }

    /** @return array<string, string> */
    public function aliases(): array
    {
        return AliasMap::all();
    }

    /** @return array<string, list<array{dst: bool, offset: int, timezone_id: string|null}>> */
    public function abbreviations(): array
    {
        /** @var array<string, list<array{dst: bool, offset: int, timezone_id: string|null}>> $abbreviations */
        $abbreviations = DateTimeZone::listAbbreviations();

        return $abbreviations;
    }

    public function countryOf(string $identifier): ?string
    {
        $this->zoneToCountry ??= $this->buildZoneToCountry();

        return $this->zoneToCountry[$identifier] ?? null;
    }

    /** @return array<string, list<string>> */
    public function countryIndex(): array
    {
        if ($this->countryIndex !== null) {
            return $this->countryIndex;
        }

        $index = [];

        foreach ($this->buildZoneToCountry() as $identifier => $country) {
            $index[$country][] = $identifier;
        }

        ksort($index);

        return $this->countryIndex = $index;
    }

    public function fingerprint(): string
    {
        if ($this->fingerprint !== null) {
            return $this->fingerprint;
        }

        $canonical = $this->identifiers();
        $withBc = $this->identifiers(includeDeprecated: true);

        $material = [
            (string) PHP_VERSION_ID,
            $this->version(),
            (string) count($canonical),
            (string) count($withBc),
            hash('xxh128', implode("\n", $withBc)),
            // Rules digest. Zone counts and the identifier list both miss the common case: a
            // government changing its own rules without any zone being added or removed. These are
            // the zones that historically do that.
            $this->rulesDigest(),
        ];

        return $this->fingerprint = substr(hash('xxh128', implode('|', $material)), 0, 12);
    }

    public function version(): string
    {
        return timezone_version_get();
    }

    /**
     * Drop entries that name a file rather than a zone.
     *
     * On a system-tzdata build `listIdentifiers()` reports whatever is in `/usr/share/zoneinfo`,
     * which includes `tzdata.zi` and `leapseconds`. They reach a picker as if they were places, and
     * `new DateTimeZone('leapseconds')` throws — so a catalogue built from the raw list is one
     * `->of()` away from a fatal error on the most common production image there is.
     *
     * No IANA identifier contains a dot, which removes the `.zi` and `.tab` files outright. The
     * remainder is only checked when it has no `/`, because a region zone is always well-formed and
     * that keeps this to a handful of constructor calls rather than six hundred.
     *
     * @param  list<string>  $identifiers
     * @return list<string>
     */
    private function onlyZones(array $identifiers): array
    {
        $zones = [];

        foreach ($identifiers as $identifier) {
            if (str_contains($identifier, '.')) {
                continue;
            }

            if (str_contains($identifier, '/')) {
                $zones[] = $identifier;

                continue;
            }

            try {
                new DateTimeZone($identifier);
                $zones[] = $identifier;
            } catch (Exception) {
                // A file, not a zone.
            }
        }

        return $zones;
    }

    /**
     * A digest of the rules most likely to change without any zone being added or removed.
     *
     * The window is a fixed absolute range, not one that slides with `time()`. A sliding window
     * looks reasonable and is a slow leak: transitions drift in and out of it as days pass, so the
     * digest — and therefore every cache key built from it — changes continuously even though the
     * database has not moved. Anchoring it means the fingerprint changes when tzdata changes, and
     * only then.
     *
     * The range is widened when it starts to age; that is a deliberate, reviewable edit.
     */
    private function rulesDigest(): string
    {
        $volatile = [
            'Africa/Cairo', 'Africa/Casablanca', 'America/Santiago', 'America/Sao_Paulo',
            'Asia/Beirut', 'Asia/Gaza', 'Asia/Tehran', 'Europe/Dublin', 'Pacific/Apia', 'Pacific/Fiji',
        ];

        $from = 1577836800;  // 2020-01-01
        $to = 2051222400;    // 2035-01-01
        $material = [];

        foreach ($volatile as $identifier) {
            $transitions = new DateTimeZone($identifier)->getTransitions($from, $to);

            foreach ($transitions === false ? [] : $transitions as $transition) {
                $material[] = $transition['ts'].':'.$transition['offset'].':'.(int) $transition['isdst'];
            }
        }

        return hash('xxh128', implode(',', $material));
    }

    /** @return array<string, string> */
    private function buildZoneToCountry(): array
    {
        if ($this->zoneToCountry !== null) {
            return $this->zoneToCountry;
        }

        $map = [];

        foreach ($this->identifiers() as $identifier) {
            $location = new DateTimeZone($identifier)->getLocation();

            if ($location === false) {
                continue;
            }

            $country = $location['country_code'];

            if ($country !== '??') {
                $map[$identifier] = $country;
            }
        }

        return $this->zoneToCountry = $map;
    }
}
