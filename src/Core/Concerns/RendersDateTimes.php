<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Concerns;

use DateTimeInterface;
use DateTimeZone;
use NoDiscard;
use Psr\Clock\ClockInterface;
use Simtabi\Laranail\Chrono\Core\Enums\NamedFormat;
use Simtabi\Laranail\Chrono\Core\Format\DateFormatter;
use Simtabi\Laranail\Chrono\Core\Humanize\Humanizer;
use Simtabi\Laranail\Chrono\Core\Support\ServiceResolver;

/**
 * Turning an instant into text — for a mailable, a notification, an export, a PDF.
 *
 * These are the two calls that look trivial and are not. `format('M j, Y')` is English forever, no
 * matter what locale the recipient reads; the right rendering for `de_DE` is `15. Jun. 2026` and for
 * `ja_JP` is `2026年6月15日`, and neither is reachable by rearranging a pattern. And "3 days ago" has
 * six plural forms in Arabic and three in Russian, which Laravel's `trans_choice` pipe syntax cannot
 * express at all.
 *
 *     final class InvoiceMail
 *     {
 *         use RendersDateTimes;
 *
 *         public function body(Invoice $invoice): string
 *         {
 *             return __('Due :date (:when)', [
 *                 'date' => $this->formatDate($invoice->due_at, NamedFormat::LongDate, $this->recipientLocale),
 *                 'when' => $this->humanizeDate($invoice->due_at, $this->recipientLocale),
 *             ]);
 *         }
 *     }
 *
 * Machine formats — `NamedFormat::Iso8601`, `Rfc3339`, `Sortable` — never localise, which is the
 * whole reason they are named separately from the human ones.
 */
trait RendersDateTimes
{
    // "3 days ago" is a statement about now, so rendering needs a clock as surely as it needs a
    // locale. Composing the clock trait here is what makes `withClock()` freeze humanised output
    // too — without it, freezing time in a test would silently leave this reading the wall clock.
    use InteractsWithClock;

    private ?DateFormatter $chronoFormatter = null;

    private ?Humanizer $chronoHumanizer = null;

    #[NoDiscard]
    public function withFormatter(DateFormatter $formatter): static
    {
        $clone = clone $this;
        $clone->chronoFormatter = $formatter;

        return $clone;
    }

    #[NoDiscard]
    public function withHumanizer(Humanizer $humanizer): static
    {
        $clone = clone $this;
        $clone->chronoHumanizer = $humanizer;

        return $clone;
    }

    protected function formatter(): DateFormatter
    {
        return $this->chronoFormatter ??= ServiceResolver::resolve(DateFormatter::class) ?? new DateFormatter;
    }

    protected function humanizer(): Humanizer
    {
        $humanizer = $this->chronoHumanizer ??= ServiceResolver::resolve(Humanizer::class) ?? new Humanizer;

        // Only when this class was *given* a clock. Otherwise the humanizer keeps whatever it came
        // with — inside an application that is already the container's clock, and overriding it
        // would discard a deliberately configured one.
        return $this->chronoClock instanceof ClockInterface
            ? $humanizer->withClock($this->chronoClock)
            : $humanizer;
    }

    /** A named format, rendered for a locale, optionally re-expressed in a zone first. */
    protected function formatDate(
        DateTimeInterface $instant,
        NamedFormat|string $format = NamedFormat::MediumDateTime,
        ?string $locale = null,
        ?DateTimeZone $zone = null,
    ): string {
        return $this->formatter()->format($instant, $format, zone: $zone, locale: $locale);
    }

    /** A raw ICU skeleton, when no named format is the shape you need. */
    protected function formatDateSkeleton(
        DateTimeInterface $instant,
        string $skeleton,
        ?string $locale = null,
        ?DateTimeZone $zone = null,
    ): string {
        return $this->formatter()->skeleton($instant, $skeleton, zone: $zone, locale: $locale);
    }

    /** "3 days ago", with the plural rules the locale actually has. */
    protected function humanizeDate(
        DateTimeInterface $instant,
        ?string $locale = null,
        ?DateTimeInterface $from = null,
    ): string {
        return $this->humanizer()->diffForHumans($instant, from: $from, locale: $locale);
    }

    /** "2 hours 15 minutes" — an elapsed length, not a point in time. */
    protected function humanizeDuration(int $seconds, ?string $locale = null, int $parts = 1): string
    {
        return $this->humanizer()->duration($seconds, locale: $locale, parts: $parts);
    }
}
