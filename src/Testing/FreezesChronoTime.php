<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Testing;

use DateTimeInterface;
use Psr\Clock\ClockInterface;
use Simtabi\Laranail\Chrono\Chrono;
use Simtabi\Laranail\Chrono\Core\Contracts\Clock;
use Simtabi\Laranail\Chrono\Core\Humanize\Humanizer;
use Simtabi\Laranail\Chrono\Core\Timezone\Timezones;
use Simtabi\Laranail\Chrono\Core\Testing\FrozenClock;
use Simtabi\Laranail\Chrono\Core\Timezone\Value\Transition;

/**
 * Stop the clock for a test, everywhere at once.
 *
 * "It renews next month" and "posted 3 hours ago" are assertions about arithmetic, and against a
 * live clock they are assertions about today. Worse, a daylight-saving test that passes in June
 * because the machine happens to be in June is not a test.
 *
 *     final class RenewalTest extends TestCase
 *     {
 *         use FreezesChronoTime;
 *
 *         public function test_it_renews_next_month(): void
 *         {
 *             $this->freezeChronoTime('2026-01-31T09:00:00Z');
 *
 *             $this->assertSame('2026-02-28', $subscription->renewsOn()->format('Y-m-d'));
 *         }
 *     }
 *
 * The frozen clock replaces the container's binding *and* the singletons already built from it, so
 * a service resolved before the freeze does not keep reading the wall clock. That rebuild is the
 * part hand-rolled freezing usually misses.
 *
 * Reaches the container through `app()` rather than `$this->app`, so it composes with Testbench,
 * Laravel's own test case and a bare Pest test alike.
 */
trait FreezesChronoTime
{
    /**
     * Freeze every "now" in the package at an instant.
     *
     * Returns the clock so a test can move it: `$clock = $this->freezeChronoTime(...)`, then
     * `$this->travelChronoTo(...)` for the next assertion.
     */
    protected function freezeChronoTime(DateTimeInterface|string $at = '2026-06-15T12:00:00Z'): FrozenClock
    {
        $clock = new FrozenClock($at);

        app()->instance(ClockInterface::class, $clock);
        app()->instance(Clock::class, $clock);

        // Anything already built captured the old clock by constructor injection, so rebinding
        // alone would leave it reading the wall clock for the rest of the test.
        foreach ([Timezones::class, Chrono::class, Humanizer::class] as $service) {
            app()->forgetInstance($service);
        }

        return $clock;
    }

    /** Move the frozen clock — for asserting what changes between two moments. */
    protected function travelChronoTo(DateTimeInterface|string $instant): FrozenClock
    {
        return $this->freezeChronoTime($instant);
    }

    /**
     * Freeze at the moment a daylight-saving transition takes effect in a zone.
     *
     * The dates move every year and differ per country, so hard-coding one in a test is how it
     * silently stops testing anything. This asks the database.
     */
    protected function freezeAtNextTransition(string $zone = 'America/New_York'): FrozenClock
    {
        $transition = app(Timezones::class)->of($zone)->nextTransition();

        // Spelled out rather than `$transition?->at ?? …`: `??` silently swallows a null-property
        // read, which reads as a guard and is not one.
        return $this->freezeChronoTime(
            $transition instanceof Transition ? $transition->at : '2026-06-15T12:00:00Z',
        );
    }
}
