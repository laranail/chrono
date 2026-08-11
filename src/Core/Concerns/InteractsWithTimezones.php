<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Concerns;

use DateTimeImmutable;
use DateTimeInterface;
use NoDiscard;
use Psr\Clock\ClockInterface;
use Simtabi\Laranail\Chrono\Core\Exception\TimezoneNotFound;
use Simtabi\Laranail\Chrono\Core\Support\ServiceResolver;
use Simtabi\Laranail\Chrono\Core\Timezone\Collection\TimezoneCollection;
use Simtabi\Laranail\Chrono\Core\Timezone\Query\TimezoneQuery;
use Simtabi\Laranail\Chrono\Core\Timezone\Timezones;
use Simtabi\Laranail\Chrono\Core\Timezone\Value\Timezone;

/**
 * Timezone resolution for any class, with no constructor argument and no container.
 *
 * The point is that a class gets the *configured* service where one exists. A helper that builds its
 * own `new Timezones` inside a Laravel application silently opts out of that application's
 * daylight-saving policy, its catalogue restrictions and its display settings — so a zone resolved
 * in that helper can disagree with the same zone resolved anywhere else in the same request. This
 * trait resolves through the container when there is one and falls back to defaults when there is
 * not, which is the only way one `use` line can be correct in both settings.
 *
 *     final class MeetingScheduler
 *     {
 *         use InteractsWithTimezones;
 *
 *         public function localStart(Meeting $meeting): DateTimeImmutable
 *         {
 *             return $this->inZone($meeting->starts_at, $meeting->attendee_timezone);
 *         }
 *     }
 *
 * Every method takes the same range of input the service does — a string, an enum case, a
 * `DateTimeZone`, a `Timezone`, anything `Stringable`.
 */
trait InteractsWithTimezones
{
    // Timezone work is full of questions about *now* — what the offset is, whether daylight saving
    // is in effect, what time it is over there. Composing the clock trait is what makes
    // `withClock()` freeze those answers too, rather than only the ones this trait asks directly.
    use InteractsWithClock;

    private ?Timezones $chronoTimezones = null;

    #[NoDiscard]
    public function withTimezones(Timezones $timezones): static
    {
        $clone = clone $this;
        $clone->chronoTimezones = $timezones;

        return $clone;
    }

    public function setTimezones(Timezones $timezones): void
    {
        $this->chronoTimezones = $timezones;
    }

    protected function timezones(): Timezones
    {
        $timezones = $this->chronoTimezones ??= ServiceResolver::resolve(Timezones::class) ?? new Timezones;

        // Applied per call rather than folded into the memo, so the order of `withClock()` and the
        // first `zone()` cannot change the answer. Only when this class was explicitly given a
        // clock: otherwise the service keeps the one it was configured with.
        return $this->chronoClock instanceof ClockInterface
            ? $timezones->withClock($this->chronoClock)
            : $timezones;
    }

    /** @throws TimezoneNotFound */
    #[NoDiscard]
    protected function zone(mixed $input): Timezone
    {
        return $this->timezones()->of($input);
    }

    #[NoDiscard]
    protected function tryZone(mixed $input): ?Timezone
    {
        return $this->timezones()->tryOf($input);
    }

    /** The canonical identifier, for writing to a column. */
    protected function zoneIdentifier(mixed $input): string
    {
        return $this->timezones()->resolve($input);
    }

    /** The current instant expressed in a zone. */
    #[NoDiscard]
    protected function nowInZone(mixed $zone): DateTimeImmutable
    {
        return $this->timezones()->now($zone);
    }

    /** The same instant, re-expressed. Never changes the moment, only how it reads. */
    #[NoDiscard]
    protected function inZone(DateTimeInterface $instant, mixed $zone): DateTimeImmutable
    {
        return $this->timezones()->convert($instant, $zone);
    }

    /** The zones this application offers, already narrowed to the configured catalogue. */
    #[NoDiscard]
    protected function zoneQuery(): TimezoneQuery
    {
        return $this->timezones()->query();
    }

    #[NoDiscard]
    protected function zonesInCountry(string $countryCode): TimezoneCollection
    {
        return $this->timezones()->inCountry($countryCode);
    }
}
