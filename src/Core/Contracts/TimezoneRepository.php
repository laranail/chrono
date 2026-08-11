<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Contracts;

use Simtabi\Laranail\Chrono\Core\Enums\Region;

/**
 * The read model over the timezone database.
 *
 * Inverting this is what lets the whole package be tested against a fixed dataset rather than
 * whatever tzdata the machine happens to carry — the difference between a suite that is stable for
 * years and one that breaks whenever a CI image is rebuilt.
 */
interface TimezoneRepository
{
    /** @return list<string> canonical identifiers, or all including aliases */
    public function identifiers(bool $includeDeprecated = false): array;

    /**
     * Whether an identifier is canonical, as opposed to a deprecated link or unknown entirely.
     *
     * A membership question rather than a list one, because the answer is needed once per
     * constructed zone. Scanning the 419-entry list each time turns building a full collection into
     * 175,000 string comparisons for a fact a hash lookup answers in one.
     */
    public function isCanonical(string $identifier): bool;

    /** @return list<string> */
    public function forCountry(string $countryCode): array;

    /** @return list<string> */
    public function forRegion(Region $region): array;

    /** @return array<string, string> alias => canonical */
    public function aliases(): array;

    /** @return array<string, list<array{dst: bool, offset: int, timezone_id: string|null}>> */
    public function abbreviations(): array;

    /** The ISO-3166 country code owning an identifier, or null for UTC, Etc/* and legacy zones. */
    public function countryOf(string $identifier): ?string;

    /** @return array<string, list<string>> country code => identifiers */
    public function countryIndex(): array;

    /**
     * A fingerprint that changes whenever the underlying date data does.
     *
     * Folded into every cache key so a tzdata upgrade rotates the whole key space with no purge
     * step. It must key on PHP's own database, never ICU's: they are shipped independently and
     * drift badly — a machine was observed running PHP tzdata 2025.3 against ICU tzdata 2019a.
     */
    public function fingerprint(): string;

    /** PHP's own tzdata release, e.g. `2025.3`. */
    public function version(): string;
}
