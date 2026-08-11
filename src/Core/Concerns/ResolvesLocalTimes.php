<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Concerns;

use DateTimeImmutable;
use DateTimeInterface;
use NoDiscard;
use Simtabi\Laranail\Chrono\Core\Config\DstPolicy;
use Simtabi\Laranail\Chrono\Core\Exception\AmbiguousLocalTime;
use Simtabi\Laranail\Chrono\Core\Exception\SkippedLocalTime;
use Simtabi\Laranail\Chrono\Core\Support\ServiceResolver;
use Simtabi\Laranail\Chrono\Core\Timezone\Value\LocalTime;

/**
 * Reading a wall clock, for a class that has to do it and would rather not think about it.
 *
 * A booking form, a payroll run and an appointment importer all take a date and a time a human
 * typed, in some zone, and have to turn it into an instant. Twice a year that is not a question with
 * one answer, and PHP picks silently — differently for different zones. This trait puts that choice
 * where the class can see it.
 *
 *     final class BookingImporter
 *     {
 *         use ResolvesLocalTimes;
 *
 *         protected function dstPolicy(): DstPolicy
 *         {
 *             return DstPolicy::strict();   // an import must not invent a time
 *         }
 *
 *         public function import(array $row): DateTimeImmutable
 *         {
 *             return $this->atLocal($row['starts_at'], $row['timezone']);
 *         }
 *     }
 *
 * Override `dstPolicy()` when this class differs from the rest of the application; leave it alone
 * and the configured pair applies. `inspectLocal()` is the branch-instead-of-catch form, which is
 * what a form wants — a user who has hit a real ambiguity in their own calendar has not made a
 * mistake, and an exception is the wrong shape for asking them which one they meant.
 *
 * Requires {@see InteractsWithTimezones}; the umbrella {@see InteractsWithChrono} pulls in both.
 */
trait ResolvesLocalTimes
{
    use InteractsWithTimezones;

    private ?DstPolicy $chronoDstPolicy = null;

    #[NoDiscard]
    public function withDstPolicy(DstPolicy $policy): static
    {
        $clone = clone $this;
        $clone->chronoDstPolicy = $policy;

        return $clone;
    }

    /** Override to fix this class's policy regardless of configuration. */
    protected function dstPolicy(): DstPolicy
    {
        return $this->chronoDstPolicy
            ??= ServiceResolver::resolve(DstPolicy::class) ?? new DstPolicy;
    }

    /**
     * A wall-clock reading in a zone, resolved under this class's policy.
     *
     * @throws SkippedLocalTime when the policy is `throw` and the reading never happened
     * @throws AmbiguousLocalTime when the policy is `throw` and it happened twice
     */
    #[NoDiscard]
    protected function atLocal(string|DateTimeInterface $local, mixed $zone): DateTimeImmutable
    {
        $policy = $this->dstPolicy();

        return $this->zone($zone)->at($local, $policy->gap, $policy->ambiguity);
    }

    /** Classify the reading without resolving or throwing — gap, ambiguous, or a single instant. */
    #[NoDiscard]
    protected function inspectLocal(string|DateTimeInterface $local, mixed $zone): LocalTime
    {
        return $this->zone($zone)->inspect($local);
    }

    protected function localTimeExists(string|DateTimeInterface $local, mixed $zone): bool
    {
        return $this->inspectLocal($local, $zone)->isValid();
    }

    protected function localTimeIsAmbiguous(string|DateTimeInterface $local, mixed $zone): bool
    {
        return $this->inspectLocal($local, $zone)->isAmbiguous();
    }
}
