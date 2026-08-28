<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Concerns;

use NoDiscard;
use DateTimeZone;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use Simtabi\Laranail\Chrono\Core\Support\ServiceResolver;
use Simtabi\Laranail\Chrono\Core\Timezone\Support\SystemClock;

/**
 * A testable "now" for any class, in one `use` line.
 *
 * The reason to bother: `new DateTimeImmutable()` inside a class is untestable by construction. A
 * test for "this subscription renews next month" has to either wait a month or accept that it is
 * really testing today's arithmetic. Everything in this package routes "now" through an injected
 * PSR-20 clock for exactly that reason, and this trait extends the same guarantee to code that
 * merely uses the package.
 *
 *     final class RenewalService
 *     {
 *         use InteractsWithClock;
 *
 *         public function renewsOn(Subscription $subscription): DateTimeImmutable
 *         {
 *             return $this->now()->modify('+1 month');
 *         }
 *     }
 *
 *     $service->withClock(new FrozenClock('2026-06-15T12:00:00Z'))->renewsOn($subscription);
 *
 * With a container present the application's bound clock is used, so freezing time in a test
 * freezes it here too. Without one, the system clock is the default and nothing needs configuring.
 */
trait InteractsWithClock
{
    private ?ClockInterface $chronoClock = null;

    /** A copy reading a different clock. Immutable, so a shared instance is never mutated. */
    #[NoDiscard]
    public function withClock(ClockInterface $clock): static
    {
        $clone = clone $this;
        $clone->chronoClock = $clock;

        return $clone;
    }

    /** Set the clock in place, for a class assembled through a constructor or a setter. */
    public function setClock(ClockInterface $clock): void
    {
        $this->chronoClock = $clock;
    }

    protected function clock(): ClockInterface
    {
        return $this->chronoClock
            ??= ServiceResolver::resolve(ClockInterface::class) ?? new SystemClock;
    }

    /** The current instant, in UTC unless a zone is named. */
    #[NoDiscard]
    protected function now(?DateTimeZone $zone = null): DateTimeImmutable
    {
        $instant = $this->clock()->now();

        return $zone instanceof DateTimeZone ? $instant->setTimezone($zone) : $instant;
    }

    protected function timestamp(): int
    {
        return $this->clock()->now()->getTimestamp();
    }
}
