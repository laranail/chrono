<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono;

use DateTimeInterface;
use NoDiscard;
use Simtabi\Laranail\Chrono\Core\Config\DisplayOptions;
use Simtabi\Laranail\Chrono\Core\Conversion\TimeConverter;
use Simtabi\Laranail\Chrono\Core\Format\DateFormatter;
use Simtabi\Laranail\Chrono\Core\Format\DateParser;
use Simtabi\Laranail\Chrono\Core\Humanize\Humanizer;
use Simtabi\Laranail\Chrono\Core\Period\Period;
use Simtabi\Laranail\Chrono\Core\Period\PeriodBuilder;
use Simtabi\Laranail\Chrono\Core\Period\PeriodCollection;
use Simtabi\Laranail\Chrono\Core\Period\Visualizer;
use Simtabi\Laranail\Chrono\Core\Presentation\TimezonePresenter;
use Simtabi\Laranail\Chrono\Core\Timezone\Timezones;
use Simtabi\Laranail\Chrono\Core\Timezone\Value\Timezone;

/**
 * The root service: one import that reaches every module.
 *
 * Each accessor returns the module's own service, so `Chrono::timezones()->query()` and a directly
 * injected `Timezones` are the same object with the same configuration. This is a front door, not a
 * layer — nothing is reimplemented here, and nothing routes through it that could not be reached by
 * injecting the module directly.
 *
 * Modules land as they are built; `calendar()`, `holidays()` and `recur()` arrive in v0.2 and v0.3.
 */
final readonly class Chrono
{
    public function __construct(
        private Timezones $timezones,
        private DateFormatter $formatter,
        private DateParser $parser,
        private Humanizer $humanizer,
        private DisplayOptions $display = new DisplayOptions,
    ) {}

    /** Interpreting, fetching and querying timezones. */
    public function timezones(): Timezones
    {
        return $this->timezones;
    }

    /** Rendering an instant for a locale. */
    public function format(): DateFormatter
    {
        return $this->formatter;
    }

    /** Reading a string into an instant, with explicit daylight-saving policies. */
    public function parse(): DateParser
    {
        return $this->parser;
    }

    /** Turning a span of time into a phrase, with correct plural rules. */
    public function humanize(): Humanizer
    {
        return $this->humanizer;
    }

    /**
     * A fluent presenter for pickers, APIs and form components.
     *
     * It lives here rather than on `Timezones` because presentation depends on the timezone module
     * and not the other way round — an accessor there would be a backwards edge, which deptrac
     * rejects.
     */
    #[NoDiscard]
    public function present(): TimezonePresenter
    {
        $presenter = new TimezonePresenter(
            $this->timezones->query(),
            offsetFormat: $this->display->offsetFormat,
            timeFormat: $this->display->timeFormat,
        );

        // Applied through the setter rather than the constructor because that is what also resolves
        // text direction; passing the locale positionally would leave an RTL locale rendering ltr.

        return $this->display->locale === null
            ? $presenter
            : $presenter->locale($this->display->locale);
    }

    /**
     * "What time is that, over there?" — for one instant or many, in one zone or many.
     *
     *     Chrono::convert('2026-06-15 09:00')->from('Africa/Nairobi')->to('Europe/London')->first();
     *     Chrono::convert($instants)->to(['Europe/London', 'Asia/Tokyo'])->table();
     *
     * @param string|DateTimeInterface|iterable<string|DateTimeInterface>|null $input
     */
    #[NoDiscard]
    public function convert(string|DateTimeInterface|iterable|null $input = null): TimeConverter
    {
        $converter = new TimeConverter($this->timezones, display: $this->display);

        return $input === null ? $converter : $converter->of($input);
    }

    /** Shorthand for the single most common call. */
    #[NoDiscard]
    public function zone(mixed $input): Timezone
    {
        return $this->timezones->of($input);
    }

    /** The offset shape, date format and locale everything this service renders defaults to. */
    public function display(): DisplayOptions
    {
        return $this->display;
    }

    /**
     * Start describing a span of time.
     *
     *     Chrono::period()->from('2026-01-01')->to('2026-03-31')->months()->build();
     *
     * A fresh builder each call, since a builder is mutable while it is being
     * filled in and sharing one would let two call sites overwrite each other.
     */
    #[NoDiscard]
    public function period(): PeriodBuilder
    {
        return new PeriodBuilder;
    }

    /** Several spans, operated on together. */
    #[NoDiscard]
    public function periods(Period ...$periods): PeriodCollection
    {
        return new PeriodCollection(...$periods);
    }

    /** Draw periods on a shared timeline, for a test failure or a dump. */
    #[NoDiscard]
    public function visualize(int $width = 27): Visualizer
    {
        return new Visualizer($width);
    }

    /** PHP's tzdata release — the version every behavioural decision keys on. */
    public function version(): string
    {
        return $this->timezones->version();
    }
}
