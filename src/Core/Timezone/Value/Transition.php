<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Timezone\Value;

use DateTimeImmutable;
use DateTimeZone;
use JsonSerializable;
use NoDiscard;

/**
 * One moment at which a zone's UTC offset changed, carrying the offset on *both* sides.
 *
 * PHP's raw `getTransitions()` entries only report the offset after the change, which makes it
 * impossible to tell a one-hour spring-forward from `Antarctica/Troll`'s two-hour jump or
 * `Pacific/Apia`'s 24-hour dateline move without pairing entries yourself. `TransitionScanner` does
 * that pairing; this is the result.
 *
 * `isDst` is taken verbatim from the database. For zones with negative DST — `Europe/Dublin` marks
 * its *winter* GMT period as the saving one — that flag is the opposite of what most people expect,
 * which is precisely why it is reported rather than reinterpreted here.
 */
final readonly class Transition implements JsonSerializable
{
    public function __construct(
        public int $timestamp,
        public DateTimeImmutable $at,
        public Offset $offsetBefore,
        public Offset $offsetAfter,
        public bool $isDst,
        public string $abbreviation,
        public string $identifier,
    ) {}

    /** Positive when the clock jumped forward, negative when it fell back. */
    #[NoDiscard]
    public function delta(): Offset
    {
        return $this->offsetAfter->minus($this->offsetBefore);
    }

    /** The clock jumped forward: a range of local times never happened. */
    public function isGap(): bool
    {
        return $this->offsetAfter->seconds > $this->offsetBefore->seconds;
    }

    /** The clock fell back: a range of local times happened twice. */
    public function isOverlap(): bool
    {
        return $this->offsetAfter->seconds < $this->offsetBefore->seconds;
    }

    /** How long the affected local range is, in seconds. 86400 for `Pacific/Apia` in 2011. */
    public function durationSeconds(): int
    {
        return abs($this->offsetAfter->seconds - $this->offsetBefore->seconds);
    }

    /** The first local instant that never existed, or null when this is not a gap. */
    #[NoDiscard]
    public function gapStart(?DateTimeZone $zone = null): ?DateTimeImmutable
    {
        return $this->isGap() ? $this->localBefore($zone) : null;
    }

    /** The first local instant after the gap, or null when this is not a gap. */
    #[NoDiscard]
    public function gapEnd(?DateTimeZone $zone = null): ?DateTimeImmutable
    {
        return $this->isGap() ? $this->localAfter($zone) : null;
    }

    /** The first local instant that happened twice, or null when this is not an overlap. */
    #[NoDiscard]
    public function overlapStart(?DateTimeZone $zone = null): ?DateTimeImmutable
    {
        return $this->isOverlap() ? $this->localAfter($zone) : null;
    }

    /** The last local instant that happened twice, or null when this is not an overlap. */
    #[NoDiscard]
    public function overlapEnd(?DateTimeZone $zone = null): ?DateTimeImmutable
    {
        return $this->isOverlap() ? $this->localBefore($zone) : null;
    }

    /** The wall-clock reading immediately before the change. */
    #[NoDiscard]
    public function localBefore(?DateTimeZone $zone = null): DateTimeImmutable
    {
        return $this->at->setTimezone($zone ?? new DateTimeZone('UTC'))
            ->modify(sprintf('%+d seconds', $this->offsetBefore->seconds - $this->offsetAfter->seconds));
    }

    /** The wall-clock reading immediately after the change. */
    #[NoDiscard]
    public function localAfter(?DateTimeZone $zone = null): DateTimeImmutable
    {
        return $this->at->setTimezone($zone ?? new DateTimeZone('UTC'));
    }

    /** @return array{timestamp: int, at: string, offset_before: string, offset_after: string, delta_seconds: int, is_dst: bool, abbreviation: string, kind: string} */
    public function toArray(): array
    {
        return [
            'timestamp' => $this->timestamp,
            'at' => $this->at->format('c'),
            'offset_before' => $this->offsetBefore->format(),
            'offset_after' => $this->offsetAfter->format(),
            'delta_seconds' => $this->offsetAfter->seconds - $this->offsetBefore->seconds,
            'is_dst' => $this->isDst,
            'abbreviation' => $this->abbreviation,
            'kind' => $this->isGap() ? 'gap' : ($this->isOverlap() ? 'overlap' : 'none'),
        ];
    }

    /** @return array{timestamp: int, at: string, offset_before: string, offset_after: string, delta_seconds: int, is_dst: bool, abbreviation: string, kind: string} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
