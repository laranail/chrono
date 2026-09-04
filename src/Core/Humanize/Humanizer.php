<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Humanize;

use NoDiscard;
use MessageFormatter;
use DateTimeInterface;
use Psr\Clock\ClockInterface;
use Simtabi\Laranail\Chrono\Core\Enums\TimeUnit;
use Simtabi\Laranail\Chrono\Core\Timezone\Support\SystemClock;

/**
 * Turns a span of time into a phrase, correctly, in any locale.
 *
 * This is a real module rather than a wrapper because PHP does not expose ICU's
 * `RelativeDateTimeFormatter`. `IntlDateFormatter::RELATIVE_FULL` exists but only reaches day
 * granularity — it will say "tomorrow at 12:00 AM" and has no way to say "3 hours ago" — so the
 * phrasing has to be assembled here.
 *
 * Every number goes through `MessageFormatter`, which applies CLDR plural rules. That is what makes
 * Arabic come out as `يومان` for two days rather than a mechanical `٢ يوم`, and Russian pick between
 * `день`, `дня` and `дней`. Nothing here counts to two and guesses.
 *
 * Differences are measured from timestamps, never from `DateTime::diff()`. Verified: diffing
 * `2026-03-08 00:00` to `2026-03-09 00:00` in `America/New_York` reports `1d 0h` for a span that is
 * really 23 hours, because `diff()` reports wall-clock difference rather than elapsed time.
 */
final readonly class Humanizer
{
    public function __construct(
        private MessageCatalogue $catalogue = new MessageCatalogue,
        private ?ClockInterface $clock = null,
        private string $defaultLocale = 'en',
    ) {}

    /**
     * "3 hours ago", "in 2 days", "just now".
     *
     * @param int $parts how many units to include — 2 gives "1 hour 20 minutes ago"
     */
    #[NoDiscard]
    public function diffForHumans(
        DateTimeInterface $instant,
        ?DateTimeInterface $from = null,
        ?string $locale = null,
        int $parts = 1,
    ): string {
        $reference = $from ?? ($this->clock ?? new SystemClock)->now();

        // Elapsed seconds, not calendar fields: a day across a DST change is not 24 hours.
        $seconds = $instant->getTimestamp() - $reference->getTimestamp();

        $resolvedLocale = $locale ?? $this->defaultLocale;

        if (abs($seconds) < TimeUnit::Second->threshold() && $parts === 1) {
            return $this->render($resolvedLocale, 'now', []);
        }

        $value = $this->duration(abs($seconds), $resolvedLocale, $parts);

        return $this->render($resolvedLocale, $seconds < 0 ? 'past' : 'future', ['value' => $value]);
    }

    /**
     * A bare duration with no past/future framing: "2 hours 5 minutes".
     *
     * @param int $parts how many units to include
     */
    #[NoDiscard]
    public function duration(int $seconds, ?string $locale = null, int $parts = 1): string
    {
        $resolvedLocale = $locale ?? $this->defaultLocale;
        $remaining = abs($seconds);

        $rendered = [];

        foreach (TimeUnit::ascending() as $ignored) {
            if (count($rendered) >= max(1, $parts) || $remaining <= 0) {
                break;
            }

            $unit = TimeUnit::forSeconds($remaining);
            $count = intdiv($remaining, $unit->seconds());

            if ($count < 1) {
                $count = 1;
                $remaining = 0;
            } else {
                $remaining -= $count * $unit->seconds();
            }

            $rendered[] = $this->render($resolvedLocale, $unit->value, ['n' => $count]);

            // Below the smallest unit there is nothing left to say.
            if ($unit === TimeUnit::Second) {
                break;
            }
        }

        if ($rendered === []) {
            $rendered[] = $this->render($resolvedLocale, TimeUnit::Second->value, ['n' => 0]);
        }

        return implode($this->separator($resolvedLocale), $rendered);
    }

    /** The unit a span would be described in, without rendering it. */
    public function unitFor(int $seconds): TimeUnit
    {
        return TimeUnit::forSeconds($seconds);
    }

    #[NoDiscard]
    public function withLocale(string $locale): self
    {
        return clone ($this, ['defaultLocale' => $locale]);
    }

    #[NoDiscard]
    public function withCatalogue(MessageCatalogue $catalogue): self
    {
        return clone ($this, ['catalogue' => $catalogue]);
    }

    #[NoDiscard]
    public function withClock(ClockInterface $clock): self
    {
        return clone ($this, ['clock' => $clock]);
    }

    /** @param array<string, int|string> $arguments */
    private function render(string $locale, string $key, array $arguments): string
    {
        ['pattern' => $pattern, 'locale' => $source] = $this->catalogue->resolve($locale, $key);

        // Formatted under the locale that *owns* the pattern, not the one that was asked for. ICU
        // picks the plural branch from the locale tag, so rendering an English pattern under an
        // unknown tag falls through to "other" and produces "1 days".
        $formatted = MessageFormatter::formatMessage($source, $pattern, $arguments);

        // ICU returns false on a malformed pattern — usually a bad override — rather than throwing.
        // Degrade to the raw pattern so a translation mistake is visible instead of blanking the UI.
        return $formatted === false ? $pattern : $formatted;
    }

    private function separator(string $locale): string
    {
        return $this->catalogue->pattern($locale, 'separator');
    }
}
