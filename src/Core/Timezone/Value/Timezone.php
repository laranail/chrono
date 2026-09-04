<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Timezone\Value;

use NoDiscard;
use Stringable;
use DateTimeZone;
use JsonSerializable;
use DateTimeImmutable;
use DateTimeInterface;
use Psr\Clock\ClockInterface;
use Simtabi\Laranail\Chrono\Core\Enums\Region;
use Simtabi\Laranail\Chrono\Core\Enums\GapPolicy;
use Simtabi\Laranail\Chrono\Core\Config\DstPolicy;
use Simtabi\Laranail\Chrono\Core\Enums\OffsetFormat;
use Simtabi\Laranail\Chrono\Core\Enums\TimezoneKind;
use Simtabi\Laranail\Chrono\Core\Enums\AmbiguityPolicy;
use Simtabi\Laranail\Chrono\Core\Timezone\Support\AliasMap;
use Simtabi\Laranail\Chrono\Core\Timezone\Support\SystemClock;
use Simtabi\Laranail\Chrono\Core\Timezone\Support\LocalTimeResolver;
use Simtabi\Laranail\Chrono\Core\Timezone\Support\TransitionScanner;
use Simtabi\Laranail\Chrono\Core\Timezone\Collection\TransitionCollection;

/**
 * A single timezone, and everything you can ask about it.
 *
 * Immutable and cheap: constructing all 419 `DateTimeZone` objects takes 1.17 ms, so this holds one
 * rather than memoising derived state, and stays genuinely readonly. The expensive call —
 * `getTransitions()`, 22.8 ms across the full set — goes through the injected scanner, which is
 * where any caching belongs.
 */
final readonly class Timezone implements JsonSerializable, Stringable
{
    public DateTimeZone $zone;

    public function __construct(
        public string $identifier,
        public TimezoneKind $kind = TimezoneKind::Canonical,
        private TransitionScanner $scanner = new TransitionScanner,
        private ?LocalTimeResolver $localTimes = null,
        private ?ClockInterface $clock = null,
        public DstPolicy $dst = new DstPolicy,
    ) {
        $this->zone = new DateTimeZone($identifier);
    }

    public function __toString(): string
    {
        return $this->identifier;
    }

    /**
     * Makes `dd($zone)` useful. Without this you get a bare `DateTimeZone` and have to go looking
     * for the offset, the DST state and the next change separately.
     */
    /** @return array<string, string> */
    public function __debugInfo(): array
    {
        $offset = $this->offset();

        return [
            'identifier'      => $this->identifier,
            'kind'            => $this->kind->value,
            'offset'          => $offset->format(OffsetFormat::Utc),
            'abbreviation'    => $this->abbreviation(),
            'dst_now'         => $this->isDst() ? 'yes' : 'no',
            'observes_dst'    => $this->observesDst() ? 'yes' : 'no',
            'next_transition' => $this->nextTransition()?->at->format('Y-m-d H:i \U\T\C') ?? 'none',
        ];
    }

    // ── identity ────────────────────────────────────────────────────────────────────────────

    public function name(): string
    {
        return $this->identifier;
    }

    public function region(): ?Region
    {
        return Region::fromIdentifier($this->identifier);
    }

    /** `Africa/Nairobi` -> `Nairobi`; `America/Argentina/Salta` -> `Salta`. */
    public function city(): string
    {
        $segment = str_contains($this->identifier, '/')
            ? substr((string) strrchr($this->identifier, '/'), 1)
            : $this->identifier;

        return str_replace('_', ' ', $segment);
    }

    public function isCanonical(): bool
    {
        return $this->kind === TimezoneKind::Canonical;
    }

    public function isDeprecated(): bool
    {
        return $this->kind === TimezoneKind::Link;
    }

    /** True for `UTC`, `Etc/*` and offset zones: no rules, no location, no transitions. */
    public function isFixedOffset(): bool
    {
        return $this->kind === TimezoneKind::Fixed;
    }

    public function isUtc(): bool
    {
        return $this->identifier === 'UTC';
    }

    /** The canonical identifier this one points at, or itself when already canonical. */
    public function canonicalIdentifier(): string
    {
        return AliasMap::canonical($this->identifier) ?? $this->identifier;
    }

    public function equals(self|string $other): bool
    {
        $otherIdentifier = $other instanceof self ? $other->identifier : $other;

        return $this->canonicalIdentifier()
            === (AliasMap::canonical($otherIdentifier) ?? $otherIdentifier);
    }

    // ── offset ──────────────────────────────────────────────────────────────────────────────

    #[NoDiscard]
    public function offset(?DateTimeInterface $at = null): Offset
    {
        return new Offset($this->zone->getOffset($at ?? $this->now()));
    }

    /** The offset when daylight saving is not in effect. */
    #[NoDiscard]
    public function standardOffset(): Offset
    {
        foreach ($this->currentEraTransitions() as $transition) {
            if (! $transition->isDst) {
                return $transition->offsetAfter;
            }
        }

        return $this->offset();
    }

    /**
     * How much the clock moves at a daylight-saving change.
     *
     * Never assume an hour. `Australia/Lord_Howe` shifts by 30 minutes and `Antarctica/Troll` by
     * two hours; both are verified in the test suite.
     */
    #[NoDiscard]
    public function dstSavings(): Offset
    {
        $largest = 0;

        foreach ($this->currentEraTransitions() as $transition) {
            $largest = max($largest, $transition->durationSeconds());
        }

        return new Offset($largest);
    }

    // ── daylight saving ─────────────────────────────────────────────────────────────────────

    /**
     * Whether daylight saving is in effect at an instant.
     *
     * Deliberately not `format('I')`. That flag is inverted for zones with negative daylight
     * saving — `Europe/Dublin` reports `'1'` in January, when it is on GMT, and `'0'` in July, when
     * it is on Irish Standard Time. Reading the transition record instead gives the flag the
     * database actually holds.
     */
    public function isDst(?DateTimeInterface $at = null): bool
    {
        $timestamp = ($at ?? $this->now())->getTimestamp();

        return $this->scanner->stateAt($this->zone, $timestamp)['is_dst'];
    }

    /** Whether the zone observes daylight saving *now* — Egypt dropped it, then reinstated it. */
    public function observesDst(): bool
    {
        return $this->currentEraTransitions() !== [];
    }

    public function observesDstIn(int $year): bool
    {
        return $this->transitionsIn($year)->isNotEmpty();
    }

    public function abbreviation(?DateTimeInterface $at = null): string
    {
        $timestamp = ($at ?? $this->now())->getTimestamp();

        return $this->scanner->stateAt($this->zone, $timestamp)['abbreviation'];
    }

    #[NoDiscard]
    public function nextTransition(?DateTimeInterface $after = null): ?Transition
    {
        $from = ($after ?? $this->now())->getTimestamp();

        foreach ([31622400, 157766400] as $window) {
            $found = $this->scanner->scan($this->zone, $from, $from + $window);

            if ($found !== []) {
                return $found[0];
            }
        }

        return null;
    }

    #[NoDiscard]
    public function previousTransition(?DateTimeInterface $before = null): ?Transition
    {
        $to = ($before ?? $this->now())->getTimestamp();

        foreach ([31622400, 157766400] as $window) {
            $found = $this->scanner->scan($this->zone, $to - $window, $to);

            if ($found !== []) {
                return $found[count($found) - 1];
            }
        }

        return null;
    }

    #[NoDiscard]
    public function transitionsBetween(DateTimeInterface $from, DateTimeInterface $to): TransitionCollection
    {
        return new TransitionCollection(
            $this->scanner->scan($this->zone, $from->getTimestamp(), $to->getTimestamp()),
        );
    }

    /** Transitions in a UTC calendar year. Documented as UTC-bounded, not local-year-bounded. */
    #[NoDiscard]
    public function transitionsIn(int $year): TransitionCollection
    {
        $utc = new DateTimeZone('UTC');

        return $this->transitionsBetween(
            new DateTimeImmutable("{$year}-01-01 00:00:00", $utc),
            new DateTimeImmutable("{$year}-12-31 23:59:59", $utc),
        );
    }

    // ── wall clock ──────────────────────────────────────────────────────────────────────────

    /**
     * Interpret a wall-clock reading in this zone.
     *
     * When given a `DateTimeInterface`, only its wall-clock fields are read and its own zone is
     * discarded — re-reading a local time in a different zone is the point of the call.
     *
     * Omitting a policy uses the application's configured pair rather than a hard-coded one, so
     * `dst.on_gap = throw` reaches every call site that never thought about daylight saving. Passing
     * one overrides it for this call only.
     */
    #[NoDiscard]
    public function at(
        string|DateTimeInterface $local,
        ?GapPolicy $gap = null,
        ?AmbiguityPolicy $ambiguity = null,
    ): DateTimeImmutable {
        return $this->resolver()->resolve(
            $local,
            $this->zone,
            $gap ?? $this->dst->gap,
            $ambiguity ?? $this->dst->ambiguity,
        );
    }

    /** The same zone under different daylight-saving policies — for one call, or one subsystem. */
    #[NoDiscard]
    public function withDst(DstPolicy $policy): self
    {
        return clone ($this, ['dst' => $policy]);
    }

    /** Classify a wall-clock reading without resolving or throwing. */
    #[NoDiscard]
    public function inspect(string|DateTimeInterface $local): LocalTime
    {
        return $this->resolver()->inspect($local, $this->zone);
    }

    public function isValidLocalTime(string|DateTimeInterface $local): bool
    {
        return $this->inspect($local)->isValid();
    }

    #[NoDiscard]
    public function convert(DateTimeInterface $instant): DateTimeImmutable
    {
        return DateTimeImmutable::createFromInterface($instant)->setTimezone($this->zone);
    }

    // ── location ────────────────────────────────────────────────────────────────────────────

    /**
     * Where this zone is, or null for a zone that is not a place.
     *
     * A deprecated identifier carries no location of its own: PHP hands back the `??`/-90/-180
     * sentinel for `Asia/Calcutta` while `Asia/Kolkata` gives India and real coordinates, even
     * though they name the same city. Following the alias means a picker that offers legacy
     * spellings still shows their country, their flag and their coordinates, and a filter by country
     * still finds them — rather than silently treating half the catalogue as placeless.
     */
    public function location(): ?Location
    {
        $location = Location::fromDateTimeZone($this->zone);

        if ($location instanceof Location) {
            return $location;
        }

        $canonical = AliasMap::canonical($this->identifier);

        return $canonical === null || $canonical === $this->identifier
            ? null
            : Location::fromDateTimeZone(new DateTimeZone($canonical));
    }

    public function countryCode(): ?string
    {
        return $this->location()?->countryCode;
    }

    // ── comparison ──────────────────────────────────────────────────────────────────────────

    /** How far ahead of `$other` this zone is at an instant. */
    #[NoDiscard]
    public function diff(self $other, ?DateTimeInterface $at = null): Offset
    {
        return $this->offset($at)->minus($other->offset($at));
    }

    public function hasSameRulesAs(self $other): bool
    {
        $window = [0, 2145916800];

        $mine = array_map(
            static fn (Transition $t): string => $t->timestamp . ':' . $t->offsetAfter->seconds,
            $this->scanner->scan($this->zone, ...$window),
        );

        $theirs = array_map(
            static fn (Transition $t): string => $t->timestamp . ':' . $t->offsetAfter->seconds,
            $other->scanner->scan($other->zone, ...$window),
        );

        return $mine === $theirs;
    }

    // ── output ──────────────────────────────────────────────────────────────────────────────

    public function toDateTimeZone(): DateTimeZone
    {
        return $this->zone;
    }

    /** @return array<string, mixed> */
    public function toArray(?DateTimeInterface $at = null): array
    {
        $offset = $this->offset($at);
        $location = $this->location();

        return [
            'identifier'   => $this->identifier,
            'canonical'    => $this->canonicalIdentifier(),
            'kind'         => $this->kind->value,
            'region'       => $this->region()?->value,
            'city'         => $this->city(),
            'country'      => $location?->countryCode,
            'offset'       => $offset->seconds,
            'offset_label' => $offset->format(),
            'abbreviation' => $this->abbreviation($at),
            'is_dst'       => $this->isDst($at),
            'observes_dst' => $this->observesDst(),
            'latitude'     => $location?->latitude,
            'longitude'    => $location?->longitude,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private function resolver(): LocalTimeResolver
    {
        return $this->localTimes ?? new LocalTimeResolver($this->scanner);
    }

    /**
     * A year either side of "now", which is what "does this zone observe daylight saving?" means in
     * practice. Egypt dropped daylight saving in 2015 and reinstated it in 2023, so answering for
     * all of history would be answering a different question.
     *
     * @return list<Transition>
     */
    private function currentEraTransitions(): array
    {
        $now = $this->now()->getTimestamp();

        return $this->scanner->scan($this->zone, $now - 31622400, $now + 63244800);
    }

    /**
     * The current instant, from the injected clock.
     *
     * Every "now" in this class routes through here. Reading the system clock directly would make
     * `offset()`, `isDst()` and `abbreviation()` non-deterministic, which for a package whose whole
     * subject is daylight saving would mean its own behaviour changed twice a year in ways no test
     * could pin down.
     */
    private function now(): DateTimeImmutable
    {
        return ($this->clock ?? new SystemClock)->now();
    }
}
