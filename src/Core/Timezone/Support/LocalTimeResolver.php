<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Timezone\Support;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use NoDiscard;
use Simtabi\Laranail\Chrono\Core\Enums\AmbiguityPolicy;
use Simtabi\Laranail\Chrono\Core\Enums\GapPolicy;
use Simtabi\Laranail\Chrono\Core\Enums\LocalTimeKind;
use Simtabi\Laranail\Chrono\Core\Timezone\Value\LocalTime;
use Simtabi\Laranail\Chrono\Core\Timezone\Value\Transition;

/**
 * Turns a wall-clock reading into the instant — or instants — it denotes in a zone.
 *
 * Twice a year every daylight-saving zone produces a reading that maps to no instant, and one that
 * maps to two. PHP resolves both silently, and its choice for the ambiguous case is not even
 * consistent between zones: verified on 8.5.3, `Europe/London 2025-10-26 01:30` yields the later
 * instant while `America/New_York 2025-11-02 01:30` yields the earlier one. Anything that stores a
 * user-entered time inherits that inconsistency without ever being told.
 */
final readonly class LocalTimeResolver
{
    /**
     * Half-width of the scan window, in seconds.
     *
     * Three days, not the ~26 hours you would size for an ordinary one-hour change. `Pacific/Apia`
     * jumped from −10:00 to +14:00 on 2011-12-30 and skipped an entire calendar day; a narrower
     * window would classify readings inside that gap as valid.
     */
    private const int WINDOW_SECONDS = 259200;

    public function __construct(private TransitionScanner $scanner = new TransitionScanner) {}

    /**
     * Classify a wall-clock reading without resolving or throwing.
     *
     * When given a `DateTimeInterface`, only its wall-clock fields are read; its own timezone is
     * discarded. Re-reading a local wall time in a different zone is the entire point of the call.
     */
    #[NoDiscard]
    public function inspect(string|DateTimeInterface $local, DateTimeZone $zone): LocalTime
    {
        $wall = $local instanceof DateTimeInterface ? $local->format('Y-m-d H:i:s') : $local;
        $identifier = $zone->getName();

        // The reading interpreted as if it were UTC. For any offset o, the instant is $naive - o.
        $naive = new DateTimeImmutable($wall, new DateTimeZone('UTC'))->getTimestamp();

        $candidates = $this->candidates($naive, $zone);

        if (count($candidates) === 1) {
            return new LocalTime(LocalTimeKind::Valid, $wall, $identifier, $candidates);
        }

        $transition = $this->nearestTransition($naive, $zone);

        return new LocalTime(
            kind: $candidates === [] ? LocalTimeKind::Gap : LocalTimeKind::Ambiguous,
            localTime: $wall,
            identifier: $identifier,
            candidates: $candidates,
            transition: $transition,
        );
    }

    /** Classify and collapse in one step. */
    #[NoDiscard]
    public function resolve(
        string|DateTimeInterface $local,
        DateTimeZone $zone,
        GapPolicy $gap = GapPolicy::Forward,
        AmbiguityPolicy $ambiguity = AmbiguityPolicy::Earlier,
    ): DateTimeImmutable {
        return $this->inspect($local, $zone)->resolve($gap, $ambiguity)->setTimezone($zone);
    }

    /**
     * Every instant whose local reading is the one we were given.
     *
     * The test is self-consistency: assume an offset, compute the instant it implies, then ask the
     * zone what its offset actually is at that instant. Only if the two agree is the candidate real.
     * Using `getOffset()` as the oracle rather than deriving interval boundaries keeps this total —
     * there are no inclusive/exclusive edge cases, no trouble with back-to-back transitions, and no
     * dependence on the shape of the transition list.
     *
     * @return list<DateTimeImmutable> chronologically ordered; 0, 1 or 2 entries in practice
     */
    private function candidates(int $naive, DateTimeZone $zone): array
    {
        $found = [];

        foreach ($this->scanner->offsetsAround($zone, $naive - self::WINDOW_SECONDS, $naive + self::WINDOW_SECONDS) as $offset) {
            $instant = $naive - $offset;

            if ($zone->getOffset(new DateTimeImmutable('@'.$instant)) === $offset) {
                // Expressed in the zone, not UTC. `resolve()` used to apply the zone at the end
                // while `inspect()` handed back raw UTC instants, so the two disagreed about
                // the same reading — and a caller showing the candidates to a user got
                // "05:30 GMT+0000 and 06:30 GMT+0000" where both should read 01:30, told apart
                // by their abbreviation.
                $found[$instant] = new DateTimeImmutable('@'.$instant)->setTimezone($zone);
            }
        }

        ksort($found);

        return array_values($found);
    }

    /** The transition responsible for a gap or overlap: the one closest to the reading. */
    private function nearestTransition(int $naive, DateTimeZone $zone): ?Transition
    {
        $nearest = null;
        $nearestDistance = PHP_INT_MAX;

        foreach ($this->scanner->scan($zone, $naive - self::WINDOW_SECONDS, $naive + self::WINDOW_SECONDS) as $transition) {
            $distance = abs($transition->timestamp - $naive);

            if ($distance < $nearestDistance) {
                $nearest = $transition;
                $nearestDistance = $distance;
            }
        }

        return $nearest;
    }
}
