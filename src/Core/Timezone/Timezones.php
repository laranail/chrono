<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Timezone;

use BackedEnum;
use DateTimeImmutable;
use DateTimeInterface;
use NoDiscard;
use Psr\Clock\ClockInterface;
use Simtabi\Laranail\Chrono\Core\Config\CatalogueOptions;
use Simtabi\Laranail\Chrono\Core\Config\DstPolicy;
use Simtabi\Laranail\Chrono\Core\Contracts\TimezoneRepository;
use Simtabi\Laranail\Chrono\Core\Enums\AmbiguityPolicy;
use Simtabi\Laranail\Chrono\Core\Enums\GapPolicy;
use Simtabi\Laranail\Chrono\Core\Enums\Region;
use Simtabi\Laranail\Chrono\Core\Enums\TimezoneKind;
use Simtabi\Laranail\Chrono\Core\Exception\TimezoneNotFound;
use Simtabi\Laranail\Chrono\Core\Timezone\Collection\TimezoneCollection;
use Simtabi\Laranail\Chrono\Core\Timezone\Query\TimezoneQuery;
use Simtabi\Laranail\Chrono\Core\Timezone\Repository\PhpTimezoneRepository;
use Simtabi\Laranail\Chrono\Core\Timezone\Resolver\Resolution;
use Simtabi\Laranail\Chrono\Core\Timezone\Resolver\ResolutionContext;
use Simtabi\Laranail\Chrono\Core\Timezone\Resolver\ResolverChain;
use Simtabi\Laranail\Chrono\Core\Timezone\Support\AliasMap;
use Simtabi\Laranail\Chrono\Core\Timezone\Support\SystemClock;
use Simtabi\Laranail\Chrono\Core\Timezone\Support\TransitionScanner;
use Simtabi\Laranail\Chrono\Core\Timezone\Value\Timezone;

/**
 * The framework-free entry point for everything timezone-related.
 *
 * Constructible with no arguments — every collaborator has a working default — so it can be used
 * from plain PHP, and bound in a container when you want to swap the repository for a fixture or
 * the clock for a frozen one.
 */
final readonly class Timezones
{
    public function __construct(
        private TimezoneRepository $repository = new PhpTimezoneRepository,
        private ?ResolverChain $resolver = null,
        private TransitionScanner $scanner = new TransitionScanner,
        private ?ClockInterface $clock = null,
        private string $fallback = 'UTC',
        private bool $strict = true,
        /** @var list<string> */
        private array $preferredCountries = [],
        private bool $allowAbbreviations = false,
        private CatalogueOptions $catalogue = new CatalogueOptions,
        private DstPolicy $dst = new DstPolicy,
        private bool $canonicaliseAliases = true,
    ) {}

    // ── interpreting ────────────────────────────────────────────────────────────────────────

    /** @throws TimezoneNotFound when nothing in the chain recognises the input */
    #[NoDiscard]
    public function of(mixed $input): Timezone
    {
        return $this->tryOf($input) ?? throw TimezoneNotFound::forInput($input);
    }

    #[NoDiscard]
    public function tryOf(mixed $input): ?Timezone
    {
        $resolution = $this->explain($input);

        return $resolution instanceof Resolution
            ? $this->make($this->preserving($input, $resolution->identifier))
            : null;
    }

    /** The identifier the input resolves to, falling back when configured to. */
    #[NoDiscard]
    public function resolve(mixed $input): string
    {
        $identifier = $this->explain($input)->identifier
            ?? ($this->strict ? throw TimezoneNotFound::forInput($input) : $this->fallback);

        return $this->preserving($input, $identifier);
    }

    /** Which strategy answered, how confident it was, and what else it could have been. */
    #[NoDiscard]
    public function explain(mixed $input): ?Resolution
    {
        return $this->chain()->resolve($input, $this->context());
    }

    /** Every zone the input could plausibly mean — what a picker shows when input is ambiguous. */
    #[NoDiscard]
    public function candidates(mixed $input): TimezoneCollection
    {
        $resolution = $this->explain($input);

        if (! $resolution instanceof Resolution) {
            return TimezoneCollection::empty();
        }

        $identifiers = $resolution->alternatives === []
            ? [$resolution->identifier]
            : $resolution->alternatives;

        return new TimezoneCollection(array_map($this->make(...), $identifiers));
    }

    public function has(mixed $input): bool
    {
        return $this->explain($input) instanceof Resolution;
    }

    /** Rewrite a deprecated identifier to its canonical form; a canonical one passes through. */
    #[NoDiscard]
    public function canonicalise(string $identifier): string
    {
        return AliasMap::canonical($identifier) ?? $identifier;
    }

    // ── fetching ────────────────────────────────────────────────────────────────────────────

    /**
     * A query already narrowed to the configured catalogue.
     *
     * Every consumer goes through here — the picker, the API, the validation rules — so they cannot
     * disagree about which zones this application offers.
     */
    #[NoDiscard]
    public function query(): TimezoneQuery
    {
        return $this->catalogue->applyTo(
            new TimezoneQuery($this->repository, $this->scanner, clock: $this->clock),
        );
    }

    /** The full catalogue, ignoring the configured restrictions. */
    #[NoDiscard]
    public function unrestrictedQuery(): TimezoneQuery
    {
        return new TimezoneQuery($this->repository, $this->scanner, clock: $this->clock);
    }

    #[NoDiscard]
    public function all(): TimezoneCollection
    {
        return $this->query()->get();
    }

    #[NoDiscard]
    public function inCountry(string $countryCode): TimezoneCollection
    {
        return $this->query()->inCountry($countryCode)->get();
    }

    #[NoDiscard]
    public function inRegion(Region|string $region): TimezoneCollection
    {
        return $this->query()->inRegion($region)->get();
    }

    public function utc(): Timezone
    {
        return $this->make('UTC');
    }

    public function fallback(): Timezone
    {
        return $this->make($this->fallback);
    }

    /** The process default. Reads it; never sets it — that would corrupt stored timestamps. */
    public function system(): Timezone
    {
        return $this->make(date_default_timezone_get());
    }

    /** @return array<string, string> */
    public function aliases(): array
    {
        return $this->repository->aliases();
    }

    // ── time ────────────────────────────────────────────────────────────────────────────────

    #[NoDiscard]
    public function now(mixed $zone = null): DateTimeImmutable
    {
        $instant = ($this->clock ?? new SystemClock)->now();

        return $zone === null ? $instant : $instant->setTimezone($this->of($zone)->zone);
    }

    #[NoDiscard]
    public function convert(DateTimeInterface $instant, mixed $to): DateTimeImmutable
    {
        return $this->of($to)->convert($instant);
    }

    // ── metadata ────────────────────────────────────────────────────────────────────────────

    /** PHP's tzdata release. Not ICU's — the two are shipped separately and drift by years. */
    public function version(): string
    {
        return $this->repository->version();
    }

    public function fingerprint(): string
    {
        return $this->repository->fingerprint();
    }

    // ── reconfiguration ─────────────────────────────────────────────────────────────────────

    #[NoDiscard]
    public function withClock(ClockInterface $clock): self
    {
        return clone ($this, ['clock' => $clock]);
    }

    #[NoDiscard]
    public function withRepository(TimezoneRepository $repository): self
    {
        return clone ($this, ['repository' => $repository]);
    }

    #[NoDiscard]
    public function preferring(string ...$countryCodes): self
    {
        return clone ($this, ['preferredCountries' => array_map(strtoupper(...), array_values($countryCodes))]);
    }

    #[NoDiscard]
    public function withCatalogue(CatalogueOptions $catalogue): self
    {
        return clone ($this, ['catalogue' => $catalogue]);
    }

    /** The daylight-saving pair every zone this service hands out will default to. */
    #[NoDiscard]
    public function withDst(DstPolicy $policy): self
    {
        return clone ($this, ['dst' => $policy]);
    }

    #[NoDiscard]
    public function onGap(GapPolicy $policy): self
    {
        return $this->withDst($this->dst->onGap($policy));
    }

    #[NoDiscard]
    public function onAmbiguity(AmbiguityPolicy $policy): self
    {
        return $this->withDst($this->dst->onAmbiguity($policy));
    }

    /**
     * Keep a deprecated identifier as written instead of rewriting it to its canonical form.
     *
     * The default rewrites, because `Asia/Calcutta` and `Asia/Kolkata` comparing unequal is a real
     * source of duplicate rows. An application migrating gradually, or one that must echo back
     * exactly what a third party sent, turns it off.
     */
    #[NoDiscard]
    public function preservingAliases(bool $preserve = true): self
    {
        return clone ($this, ['canonicaliseAliases' => ! $preserve]);
    }

    #[NoDiscard]
    public function lenient(): self
    {
        return clone ($this, ['strict' => false]);
    }

    #[NoDiscard]
    public function allowingAbbreviations(bool $allow = true): self
    {
        return clone ($this, ['allowAbbreviations' => $allow]);
    }

    /**
     * With canonicalisation off, an input that is already a usable identifier is kept as written.
     *
     * Only an exact IANA alias qualifies. Anything else the chain understood — an abbreviation, a
     * country code, a Windows name — has no identity of its own to preserve, so it still resolves.
     */
    private function preserving(mixed $input, string $identifier): string
    {
        if ($this->canonicaliseAliases) {
            return $identifier;
        }

        // An enum case spells an identifier just as a string does, so `TimezoneLegacy::AsiaCalcutta`
        // is preserved on the same terms as the literal.
        $written = match (true) {
            is_string($input) => $input,
            $input instanceof BackedEnum && is_string($input->value) => $input->value,
            default => null,
        };

        if ($written === null || $written === $identifier) {
            return $identifier;
        }

        return AliasMap::isAlias($written) ? $written : $identifier;
    }

    private function make(string $identifier): Timezone
    {
        return new Timezone(
            $identifier,
            $this->kindOf($identifier),
            $this->scanner,
            clock: $this->clock,
            dst: $this->dst,
        );
    }

    private function kindOf(string $identifier): TimezoneKind
    {
        if (AliasMap::isAlias($identifier)) {
            return TimezoneKind::Link;
        }

        if ($identifier === 'UTC' || str_starts_with($identifier, 'Etc/') || str_starts_with($identifier, '+') || str_starts_with($identifier, '-')) {
            return TimezoneKind::Fixed;
        }

        return $this->repository->isCanonical($identifier)
            ? TimezoneKind::Canonical
            : TimezoneKind::Legacy;
    }

    private function chain(): ResolverChain
    {
        return $this->resolver ?? ResolverChain::default();
    }

    private function context(): ResolutionContext
    {
        return new ResolutionContext(
            repository: $this->repository,
            preferredCountries: $this->preferredCountries,
            strict: $this->strict,
            allowAbbreviations: $this->allowAbbreviations,
        );
    }
}
