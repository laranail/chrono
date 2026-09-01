<?php

declare(strict_types=1);

use Simtabi\Laranail\Chrono\Core\Enums\AmbiguityPolicy;
use Simtabi\Laranail\Chrono\Core\Enums\GapPolicy;
use Simtabi\Laranail\Chrono\Core\Exception\AmbiguousLocalTime;
use Simtabi\Laranail\Chrono\Core\Exception\SkippedLocalTime;
use Simtabi\Laranail\Chrono\Core\Timezone\Support\LocalTimeResolver;

beforeEach(function (): void {
    $this->resolver = new LocalTimeResolver;
    $this->newYork = zone('America/New_York');
});

describe('daylight-saving gaps', function (): void {
    it('recognises a local time that never happened', function (): void {
        $status = $this->resolver->inspect('2026-03-08 02:30', $this->newYork);

        expect($status->isGap())->toBeTrue()
            ->and($status->candidates)->toBeEmpty();
    });

    it('shifts forward by default, matching what PHP already does', function (): void {
        $ours = $this->resolver->resolve('2026-03-08 02:30', $this->newYork, GapPolicy::Forward);
        $php = new DateTimeImmutable('2026-03-08 02:30', $this->newYork);

        // Adopting this package must not change behaviour until a caller opts in.
        expect($ours->getTimestamp())->toBe($php->getTimestamp())
            ->and($ours->format('H:i T'))->toBe('03:30 EDT');
    });

    it('can shift backward instead, preserving duration when pairing times', function (): void {
        expect($this->resolver->resolve('2026-03-08 02:30', $this->newYork, GapPolicy::Backward)->format('H:i T'))
            ->toBe('01:30 EST');
    });

    it('refuses when told to', function (): void {
        (void) $this->resolver->resolve('2026-03-08 02:30', $this->newYork, GapPolicy::Throw);
    })->throws(SkippedLocalTime::class);
});

describe('daylight-saving ambiguity', function (): void {
    it('recognises a local time that happened twice', function (): void {
        $status = $this->resolver->inspect('2026-11-01 01:30', $this->newYork);

        expect($status->isAmbiguous())->toBeTrue()
            ->and($status->candidates)->toHaveCount(2);
    });

    it('picks the occurrence the caller asked for', function (): void {
        expect($this->resolver->resolve('2026-11-01 01:30', $this->newYork, ambiguity: AmbiguityPolicy::Earlier)->format('T'))
            ->toBe('EDT')
            ->and($this->resolver->resolve('2026-11-01 01:30', $this->newYork, ambiguity: AmbiguityPolicy::Later)->format('T'))
            ->toBe('EST');
    });

    it('refuses when told to', function (): void {
        (void) $this->resolver->resolve('2026-11-01 01:30', $this->newYork, ambiguity: AmbiguityPolicy::Throw);
    })->throws(AmbiguousLocalTime::class);

    /**
     * The reason this package exists. PHP resolves an ambiguous reading silently *and*
     * inconsistently: London yields the later instant, New York the earlier one, from the same
     * build. A booking stored in two cities gets opposite disambiguation and nothing says so.
     */
    it('is consistent across zones where PHP is not', function (): void {
        $london = zone('Europe/London');
        $newYork = zone('America/New_York');

        expect(new DateTimeImmutable('2025-10-26 01:30', $london)->format('T'))->toBe('GMT')
            ->and(new DateTimeImmutable('2025-11-02 01:30', $newYork)->format('T'))->toBe('EDT');

        // Ours agree with each other, whichever policy is chosen.
        expect($this->resolver->resolve('2025-10-26 01:30', $london, ambiguity: AmbiguityPolicy::Earlier)->format('T'))->toBe('BST')
            ->and($this->resolver->resolve('2025-11-02 01:30', $newYork, ambiguity: AmbiguityPolicy::Earlier)->format('T'))->toBe('EDT')
            ->and($this->resolver->resolve('2025-10-26 01:30', $london, ambiguity: AmbiguityPolicy::Later)->format('T'))->toBe('GMT')
            ->and($this->resolver->resolve('2025-11-02 01:30', $newYork, ambiguity: AmbiguityPolicy::Later)->format('T'))->toBe('EST');
    });
});

it('treats ordinary times as unambiguous', function (): void {
    expect($this->resolver->inspect('2026-06-15 12:00', $this->newYork)->isValid())->toBeTrue();
});

it('treats every time in a rule-less zone as unambiguous', function (string $identifier): void {
    expect($this->resolver->inspect('2026-03-08 02:30', zone($identifier))->isValid())->toBeTrue();
})->with('rule-less zones');

/**
 * The scan window is three days rather than the ~26 hours an ordinary change would need, because
 * Samoa skipped a whole calendar day in 2011.
 */
it('sees a gap a day wide', function (): void {
    expect($this->resolver->inspect('2011-12-30 10:00', zone('Pacific/Apia'))->isGap())->toBeTrue();
});

/**
 * `inspect()` and `resolve()` must describe the same reading the same way. They did not: `resolve()`
 * applied the zone at the end while `inspect()` handed back raw UTC instants, so a caller offering
 * the candidates to a user saw two UTC times an hour apart rather than the same local reading told
 * apart by its abbreviation.
 */
it('reports candidates in the zone, not in UTC', function (): void {
    $status = $this->resolver->inspect('2026-11-01 01:30', $this->newYork);

    expect($status->earlier()->format('H:i T'))->toBe('01:30 EDT')
        ->and($status->later()->format('H:i T'))->toBe('01:30 EST')
        ->and($status->earlier()->getTimezone()->getName())->toBe('America/New_York');
});

it('agrees with resolve() about the instant', function (): void {
    $status = $this->resolver->inspect('2026-11-01 01:30', $this->newYork);
    $resolved = $this->resolver->resolve('2026-11-01 01:30', $this->newYork);

    expect($status->earlier()->getTimestamp())->toBe($resolved->getTimestamp());
});
