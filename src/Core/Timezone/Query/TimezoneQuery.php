<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Timezone\Query;

use Countable;
use DateTimeInterface;
use Generator;
use IteratorAggregate;
use NoDiscard;
use Psr\Clock\ClockInterface;
use Simtabi\Laranail\Chrono\Core\Contracts\TimezoneRepository;
use Simtabi\Laranail\Chrono\Core\Enums\Region;
use Simtabi\Laranail\Chrono\Core\Enums\SelectShape;
use Simtabi\Laranail\Chrono\Core\Enums\TimezoneField;
use Simtabi\Laranail\Chrono\Core\Enums\TimezoneKind;
use Simtabi\Laranail\Chrono\Core\Exception\TimezoneNotFound;
use Simtabi\Laranail\Chrono\Core\Timezone\Collection\TimezoneCollection;
use Simtabi\Laranail\Chrono\Core\Timezone\Support\AliasMap;
use Simtabi\Laranail\Chrono\Core\Timezone\Support\OffsetParser;
use Simtabi\Laranail\Chrono\Core\Timezone\Support\TransitionScanner;
use Simtabi\Laranail\Chrono\Core\Timezone\Value\Timezone;
use Traversable;

/**
 * A fluent, immutable query over the timezone catalogue.
 *
 * Every builder method returns a clone, so a query can be shared, stored and branched without one
 * caller's filter leaking into another's. Each is marked `#[\NoDiscard]` because `$q->inCountry('KE');`
 * as a statement is a silent no-op — the exact mistake an immutable builder invites.
 *
 * Filters are applied cheapest-first: string tests on the identifier, then index lookups, then
 * anything that has to compute an offset, then user callbacks. `lazy()` streams and only builds a
 * `Timezone` when a predicate actually needs one.
 *
 * `skip()` and `take()`, never `offset()` and `limit()` — `Offset` is already a UTC-offset value
 * object here, and a query method of the same name would be a permanent source of confusion.
 *
 * @implements IteratorAggregate<string, Timezone>
 */
final readonly class TimezoneQuery implements Countable, IteratorAggregate
{
    /**
     * @param list<Region> $regions
     * @param list<string> $countries
     * @param list<int> $offsets
     * @param list<string> $abbreviations
     * @param list<string> $only
     * @param list<string> $except
     * @param list<callable(Timezone): bool> $callbacks
     */
    public function __construct(
        private TimezoneRepository $repository,
        private TransitionScanner $scanner = new TransitionScanner,
        private array $regions = [],
        private array $countries = [],
        private array $offsets = [],
        private array $abbreviations = [],
        private array $only = [],
        private array $except = [],
        private array $callbacks = [],
        private ?string $matching = null,
        private ?bool $observingDst = null,
        private ?bool $currentlyInDst = null,
        private bool $includeDeprecated = false,
        private bool $includeFixed = false,
        private bool $includeUtc = true,
        private ?int $minOffset = null,
        private ?int $maxOffset = null,
        private ?TimezoneField $sortField = null,
        private bool $sortDescending = false,
        private ?int $skip = null,
        private ?int $limit = null,
        private ?DateTimeInterface $asOf = null,
        private ?ClockInterface $clock = null,
    ) {}

    // ── filters ─────────────────────────────────────────────────────────────────────────────

    #[NoDiscard]
    public function inRegion(Region|string ...$regions): self
    {
        $resolved = array_values(array_filter(array_map(
            static fn (Region|string $r): ?Region => $r instanceof Region ? $r : Region::tryFrom($r),
            $regions,
        )));

        return clone ($this, ['regions' => [...$this->regions, ...$resolved]]);
    }

    #[NoDiscard]
    public function inCountry(string ...$codes): self
    {
        return clone ($this, [
            'countries' => array_values([...$this->countries, ...array_map(strtoupper(...), $codes)]),
        ]);
    }

    #[NoDiscard]
    public function withOffset(int|string ...$offsets): self
    {
        $seconds = array_values(array_filter(array_map(
            static fn (int|string $o): ?int => is_int($o) ? $o : OffsetParser::tryParse($o),
            $offsets,
        ), static fn (?int $o): bool => $o !== null));

        return clone ($this, ['offsets' => [...$this->offsets, ...$seconds]]);
    }

    #[NoDiscard]
    public function offsetBetween(int|string $min, int|string $max): self
    {
        return clone ($this, [
            'minOffset' => is_int($min) ? $min : OffsetParser::parse($min),
            'maxOffset' => is_int($max) ? $max : OffsetParser::parse($max),
        ]);
    }

    #[NoDiscard]
    public function withAbbreviation(string ...$abbreviations): self
    {
        return clone ($this, [
            'abbreviations' => array_values([...$this->abbreviations, ...array_map(strtoupper(...), $abbreviations)]),
        ]);
    }

    /** Zones that do, or do not, observe daylight saving in the current era. */
    #[NoDiscard]
    public function observingDst(bool $observing = true): self
    {
        return clone ($this, ['observingDst' => $observing]);
    }

    /** Zones for which daylight saving is in effect right now, or at `asOf()`. */
    #[NoDiscard]
    public function currentlyInDst(bool $inDst = true): self
    {
        return clone ($this, ['currentlyInDst' => $inDst]);
    }

    /** Case-insensitive substring match over the identifier, city and country. */
    #[NoDiscard]
    public function matching(string $term): self
    {
        return clone ($this, ['matching' => strtolower(trim($term))]);
    }

    #[NoDiscard]
    public function only(string ...$identifiers): self
    {
        return clone ($this, ['only' => array_values([...$this->only, ...$identifiers])]);
    }

    #[NoDiscard]
    public function except(string ...$identifiers): self
    {
        return clone ($this, ['except' => array_values([...$this->except, ...$identifiers])]);
    }

    /** @param callable(Timezone): bool $predicate */
    #[NoDiscard]
    public function where(callable $predicate): self
    {
        return clone ($this, ['callbacks' => [...$this->callbacks, $predicate]]);
    }

    #[NoDiscard]
    public function includeDeprecated(bool $include = true): self
    {
        return clone ($this, ['includeDeprecated' => $include]);
    }

    /** `Etc/*` and the other fixed-offset zones, which live only in the backward-compatible list. */
    #[NoDiscard]
    public function includeFixed(bool $include = true): self
    {
        return clone ($this, ['includeFixed' => $include]);
    }

    #[NoDiscard]
    public function includeUtc(bool $include = true): self
    {
        return clone ($this, ['includeUtc' => $include]);
    }

    // ── ordering, slicing, evaluation context ───────────────────────────────────────────────

    #[NoDiscard]
    public function orderBy(TimezoneField $field, bool $descending = false): self
    {
        return clone ($this, ['sortField' => $field, 'sortDescending' => $descending]);
    }

    #[NoDiscard]
    public function orderByOffset(bool $descending = false): self
    {
        return $this->orderBy(TimezoneField::Offset, $descending);
    }

    #[NoDiscard]
    public function orderByIdentifier(bool $descending = false): self
    {
        return $this->orderBy(TimezoneField::Identifier, $descending);
    }

    #[NoDiscard]
    public function take(int $count): self
    {
        return clone ($this, ['limit' => $count]);
    }

    #[NoDiscard]
    public function skip(int $count): self
    {
        return clone ($this, ['skip' => $count]);
    }

    /** Evaluate offsets and DST state at this instant rather than now. */
    #[NoDiscard]
    public function asOf(DateTimeInterface $instant): self
    {
        return clone ($this, ['asOf' => $instant]);
    }

    // ── terminals ───────────────────────────────────────────────────────────────────────────

    #[NoDiscard]
    public function get(): TimezoneCollection
    {
        /** @var list<Timezone> $materialised */
        $materialised = iterator_to_array($this->lazy(), preserve_keys: false);

        $collection = new TimezoneCollection($materialised);

        if ($this->sortField instanceof TimezoneField) {
            $collection = $collection->sortBy($this->sortField, $this->sortDescending, $this->asOf);
        }

        if ($this->skip !== null) {
            $collection = $collection->skip($this->skip);
        }

        if ($this->limit !== null) {
            return $collection->take($this->limit);
        }

        return $collection;
    }

    /** Streams identifiers through the filters, materialising a `Timezone` only when needed. */
    public function lazy(): Generator
    {
        foreach ($this->candidateIdentifiers() as $identifier) {
            if (! $this->passesIdentifierFilters($identifier)) {
                continue;
            }

            $timezone = new Timezone($identifier, $this->kindOf($identifier), $this->scanner, clock: $this->clock);

            if ($this->passesValueFilters($timezone)) {
                yield $identifier => $timezone;
            }
        }
    }

    public function first(): ?Timezone
    {
        /** @var Timezone $timezone */
        foreach ($this->lazy() as $timezone) {
            return $timezone;
        }

        return null;
    }

    #[NoDiscard]
    public function firstOrFail(): Timezone
    {
        return $this->first() ?? throw TimezoneNotFound::forQuery();
    }

    public function exists(): bool
    {
        return $this->first() instanceof Timezone;
    }

    /**
     * Respects `skip()` and `take()`, so `count()` always agrees with `get()->count()`.
     *
     * SQL builders traditionally have `COUNT` ignore `LIMIT`, but here that would mean
     * `->take(5)->count()` returning 419 — a difference nobody expects and everybody trips over.
     */
    public function count(): int
    {
        $counted = 0;

        foreach ($this->lazy() as $ignored) {
            $counted++;
        }

        $counted = max(0, $counted - ($this->skip ?? 0));

        return $this->limit === null ? $counted : max(0, min($counted, $this->limit));
    }

    /** @return list<string> */
    public function identifiers(): array
    {
        return $this->get()->identifiers();
    }

    /** @return array<string, TimezoneCollection> */
    public function groupBy(TimezoneField $field): array
    {
        return $this->get()->groupBy($field, $this->asOf);
    }

    /** @return array<string, mixed>|list<array<string, mixed>> */
    #[NoDiscard]
    public function toSelectOptions(SelectShape $shape = SelectShape::Grouped, bool $rtl = false): array
    {
        return $this->get()->toSelectOptions($shape, $this->asOf, $rtl);
    }

    /** @return list<array<string, mixed>> */
    public function toArray(): array
    {
        return $this->get()->toArray($this->asOf);
    }

    /** @return Traversable<string, Timezone> */
    public function getIterator(): Traversable
    {
        return $this->lazy();
    }

    // ── internals ───────────────────────────────────────────────────────────────────────────

    /** @return list<string> */
    private function candidateIdentifiers(): array
    {
        if ($this->only !== []) {
            return $this->only;
        }

        if ($this->countries !== []) {
            $identifiers = [];

            foreach ($this->countries as $country) {
                $identifiers = [...$identifiers, ...$this->repository->forCountry($country)];
            }

            return array_values(array_unique($identifiers));
        }

        if ($this->regions !== []) {
            $identifiers = [];

            foreach ($this->regions as $region) {
                $identifiers = [...$identifiers, ...$this->repository->forRegion($region)];
            }

            return array_values(array_unique($identifiers));
        }

        return $this->repository->identifiers(
            includeDeprecated: $this->includeDeprecated || $this->includeFixed,
        );
    }

    private function passesIdentifierFilters(string $identifier): bool
    {
        if (in_array($identifier, $this->except, true)) {
            return false;
        }

        $isFixed = $identifier === 'UTC' || str_starts_with($identifier, 'Etc/');

        if ($identifier === 'UTC' && ! $this->includeUtc) {
            return false;
        }

        if ($isFixed && $identifier !== 'UTC' && ! $this->includeFixed) {
            return false;
        }

        return $this->includeDeprecated || ! AliasMap::isAlias($identifier);
    }

    private function passesValueFilters(Timezone $timezone): bool
    {
        if ($this->matching !== null) {
            $haystack = strtolower(implode(' ', array_filter(
                [$timezone->identifier, $timezone->city(), $timezone->countryCode()],
                static fn (?string $part): bool => $part !== null && $part !== '',
            )));

            if (! str_contains($haystack, $this->matching)) {
                return false;
            }
        }

        if ($this->offsets !== [] && ! in_array($timezone->offset($this->asOf)->seconds, $this->offsets, true)) {
            return false;
        }

        if ($this->minOffset !== null || $this->maxOffset !== null) {
            $seconds = $timezone->offset($this->asOf)->seconds;

            if ($this->minOffset !== null && $seconds < $this->minOffset) {
                return false;
            }

            if ($this->maxOffset !== null && $seconds > $this->maxOffset) {
                return false;
            }
        }

        if ($this->abbreviations !== []
            && ! in_array(strtoupper($timezone->abbreviation($this->asOf)), $this->abbreviations, true)) {
            return false;
        }

        if ($this->observingDst !== null && $timezone->observesDst() !== $this->observingDst) {
            return false;
        }

        if ($this->currentlyInDst !== null && $timezone->isDst($this->asOf) !== $this->currentlyInDst) {
            return false;
        }

        return array_all($this->callbacks, fn (callable $callback) => $callback($timezone));
    }

    private function kindOf(string $identifier): TimezoneKind
    {
        if (AliasMap::isAlias($identifier)) {
            return TimezoneKind::Link;
        }

        if ($identifier === 'UTC' || str_starts_with($identifier, 'Etc/')) {
            return TimezoneKind::Fixed;
        }

        return in_array($identifier, $this->repository->identifiers(), true)
            ? TimezoneKind::Canonical
            : TimezoneKind::Legacy;
    }
}
