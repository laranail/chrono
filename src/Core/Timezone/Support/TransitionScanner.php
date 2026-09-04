<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Timezone\Support;

use NoDiscard;
use DateTimeZone;
use DateTimeImmutable;
use Simtabi\Laranail\Chrono\Core\Timezone\Value\Offset;
use Simtabi\Laranail\Chrono\Core\Timezone\Value\Transition;

/**
 * The single place in this package that calls `DateTimeZone::getTransitions()`.
 *
 * It exists because the raw return value cannot be consumed safely as-is. Three verified quirks:
 *
 * 1. **It returns `false`, not an empty array, for offset- and abbreviation-type zones.** Both
 *    `new DateTimeZone('+03:00')` and `new DateTimeZone('CEST')` construct successfully and both
 *    return `false` here. Since resolving a user-supplied `+03:00` deliberately produces such a
 *    zone, every DST code path is otherwise one call away from `count(false)`.
 * 2. **The first entry is synthetic.** It sits at exactly `$from` and describes the state *at* that
 *    instant rather than a change. Emitting it as a transition invents an event that never happened.
 * 3. **Entries can be exact duplicates.** When `$from` lands precisely on a real transition, entries
 *    0 and 1 carry the same `ts`.
 *
 * On top of that, PHP only reports the offset *after* each change, so this pairs consecutive entries
 * to give both sides — without which a 30-minute (`Australia/Lord_Howe`), two-hour
 * (`Antarctica/Troll`) and 24-hour (`Pacific/Apia`) change are indistinguishable from an hour.
 */
final readonly class TransitionScanner
{
    /**
     * Real transitions in `[$from, $to]`, in chronological order.
     *
     * @return list<Transition>
     */
    #[NoDiscard]
    public function scan(DateTimeZone $zone, int $from, int $to): array
    {
        $raw = $zone->getTransitions($from, $to);

        // Quirk 1: fixed-offset and abbreviation zones have no rules at all.
        if ($raw === false || $raw === []) {
            return [];
        }

        $identifier = $zone->getName();

        // Quirk 2: entry zero states the offset in force at $from; it is not a change.
        $previousOffset = $raw[0]['offset'];
        $previousTimestamp = $raw[0]['ts'];

        $transitions = [];

        foreach (array_slice($raw, 1) as $entry) {
            $timestamp = $entry['ts'];
            $offset = $entry['offset'];

            // Quirk 3: a duplicate of the synthetic head, or a re-declaration that changes nothing.
            if ($timestamp === $previousTimestamp || $offset === $previousOffset) {
                $previousTimestamp = $timestamp;

                continue;
            }

            $transitions[] = new Transition(
                timestamp: $timestamp,
                at: new DateTimeImmutable('@' . $timestamp),
                offsetBefore: new Offset($previousOffset),
                offsetAfter: new Offset($offset),
                isDst: $entry['isdst'],
                abbreviation: $entry['abbr'],
                identifier: $identifier,
            );

            $previousOffset = $offset;
            $previousTimestamp = $timestamp;
        }

        return $transitions;
    }

    /**
     * The offset, DST flag and abbreviation in force at an instant, read from the synthetic head
     * that quirk 2 describes — the one situation where that entry is exactly what we want.
     *
     * @return array{offset: int, is_dst: bool, abbreviation: string}
     */
    #[NoDiscard]
    public function stateAt(DateTimeZone $zone, int $timestamp): array
    {
        $raw = $zone->getTransitions($timestamp, $timestamp);

        if ($raw === false || $raw === []) {
            // A fixed-offset zone: no rules, so ask the zone directly.
            return [
                'offset'       => $zone->getOffset(new DateTimeImmutable('@' . $timestamp)),
                'is_dst'       => false,
                'abbreviation' => $zone->getName(),
            ];
        }

        return [
            'offset'       => $raw[0]['offset'],
            'is_dst'       => $raw[0]['isdst'],
            'abbreviation' => $raw[0]['abbr'],
        ];
    }

    /**
     * Every distinct offset in force across a window, used to seed local-time resolution.
     *
     * @return list<int>
     */
    #[NoDiscard]
    public function offsetsAround(DateTimeZone $zone, int $from, int $to): array
    {
        $offsets = [$this->stateAt($zone, $from)['offset']];

        foreach ($this->scan($zone, $from, $to) as $transition) {
            $offsets[] = $transition->offsetAfter->seconds;
        }

        return array_values(array_unique($offsets));
    }

    /** Whether the zone has any rules at all. False for `UTC`, `Etc/*`, `+03:00` and `CEST`. */
    public function hasRules(DateTimeZone $zone): bool
    {
        $raw = $zone->getTransitions(0, 2145916800);

        return $raw !== false && count($raw) > 1;
    }
}
