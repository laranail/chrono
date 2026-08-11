<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Concerns;

use DateTimeInterface;
use NoDiscard;
use Simtabi\Laranail\Chrono\Core\Config\DisplayOptions;
use Simtabi\Laranail\Chrono\Core\Conversion\ConvertedTime;
use Simtabi\Laranail\Chrono\Core\Conversion\TimeConverter;
use Simtabi\Laranail\Chrono\Core\Support\ServiceResolver;

/**
 * "What time is that, over there?" for a class that asks it more than once.
 *
 * The fan-out is the reason this is worth a trait rather than a call. A notification that has to say
 * when a meeting starts for each of five attendees wants one instant expressed five ways, and
 * writing that as five conversions gives five chances to pass a slightly different instant. Every
 * conversion from one builder shares one instant by construction.
 *
 *     final class MeetingDigest
 *     {
 *         use ConvertsTimezones;
 *
 *         public function rows(Meeting $meeting): array
 *         {
 *             return $this->convertTime($meeting->starts_at)
 *                 ->to($meeting->attendees->pluck('timezone'))
 *                 ->keyed();
 *         }
 *     }
 *
 * Requires {@see InteractsWithTimezones}; the umbrella {@see InteractsWithChrono} pulls in both.
 */
trait ConvertsTimezones
{
    use InteractsWithTimezones;

    private ?DisplayOptions $chronoDisplay = null;

    #[NoDiscard]
    public function withDisplayOptions(DisplayOptions $display): static
    {
        $clone = clone $this;
        $clone->chronoDisplay = $display;

        return $clone;
    }

    protected function displayOptions(): DisplayOptions
    {
        return $this->chronoDisplay ??= ServiceResolver::resolve(DisplayOptions::class) ?? new DisplayOptions;
    }

    /**
     * A converter, optionally already loaded with what to convert.
     *
     * @param string|DateTimeInterface|iterable<string|DateTimeInterface>|null $input
     */
    #[NoDiscard]
    protected function convertTime(string|DateTimeInterface|iterable|null $input = null): TimeConverter
    {
        $converter = new TimeConverter($this->timezones(), display: $this->displayOptions());

        return $input === null ? $converter : $converter->of($input);
    }

    /** The shorthand for the single most common case: one instant, one zone. */
    #[NoDiscard]
    protected function convertOne(DateTimeInterface $instant, mixed $zone): ?ConvertedTime
    {
        return $this->convertTime($instant)->to($zone)->first();
    }
}
