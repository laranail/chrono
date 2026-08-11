<?php

declare(strict_types=1);

use Simtabi\Laranail\Chrono\Core\Timezone\Support\TransitionScanner;

beforeEach(function (): void {
    $this->scanner = new TransitionScanner;
});

/**
 * `DateTimeZone::getTransitions()` returns `false` — not an empty array — for offset and
 * abbreviation zones. Since resolving a user-supplied `+03:00` deliberately produces one of those,
 * every daylight-saving path would otherwise be a `count(false)` TypeError away from a crash.
 */
it('returns an empty list for zones that have no rules', function (string $identifier): void {
    expect($this->scanner->scan(zone($identifier), 0, 2145916800))->toBe([])
        ->and($this->scanner->hasRules(zone($identifier)))->toBeFalse();
})->with('rule-less zones');

it('finds both transitions in a northern year', function (): void {
    $transitions = $this->scanner->scan(zone('America/New_York'), strtotime('2026-01-01'), strtotime('2027-01-01'));

    expect($transitions)->toHaveCount(2)
        ->and($transitions[0]->isGap())->toBeTrue()
        ->and($transitions[1]->isOverlap())->toBeTrue();
});

/**
 * PHP reports only the offset *after* each change, so without pairing consecutive entries a
 * 30-minute shift, a two-hour shift and a whole skipped day all look alike.
 */
it('measures how far the clock actually moved', function (string $identifier, int $expected): void {
    $transitions = $this->scanner->scan(zone($identifier), strtotime('2026-01-01'), strtotime('2027-01-01'));

    $largest = 0;

    foreach ($transitions as $transition) {
        $largest = max($largest, $transition->durationSeconds());
    }

    expect($largest)->toBe($expected);
})->with('dst savings');

it('sees the whole day Samoa skipped in 2011', function (): void {
    $transitions = $this->scanner->scan(zone('Pacific/Apia'), strtotime('2011-12-01'), strtotime('2012-01-15'));

    expect($transitions[0]->durationSeconds())->toBe(86400)
        ->and($transitions[0]->isGap())->toBeTrue();
});

it('reports the state in force at an instant', function (): void {
    $state = $this->scanner->stateAt(zone('Africa/Nairobi'), utc('2026-06-15 12:00')->getTimestamp());

    expect($state['offset'])->toBe(10800)
        ->and($state['abbreviation'])->toBe('EAT')
        ->and($state['is_dst'])->toBeFalse();
});
