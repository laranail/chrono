<?php

declare(strict_types=1);

use Simtabi\Laranail\Chrono\Core\Enums\Region;
use Simtabi\Laranail\Chrono\Core\Timezone\Repository\PhpTimezoneRepository;

beforeEach(function (): void {
    $this->repository = new PhpTimezoneRepository;
});

it('separates canonical identifiers from the backward-compatible list', function (): void {
    $canonical = $this->repository->identifiers();
    $withDeprecated = $this->repository->identifiers(includeDeprecated: true);

    expect($canonical)->toBe(DateTimeZone::listIdentifiers(DateTimeZone::ALL))
        ->and($withDeprecated)->toBe(DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC))
        ->and(count($withDeprecated))->toBeGreaterThan(count($canonical));
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
