<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Tests\Fixtures\Core;

use DateTimeImmutable;
use DateTimeInterface;
use Simtabi\Laranail\Chrono\Core\Concerns\InteractsWithChrono;
use Simtabi\Laranail\Chrono\Core\Conversion\ConvertedTime;
use Simtabi\Laranail\Chrono\Core\Enums\NamedFormat;

/**
 * A plain PHP class that uses the umbrella trait — no framework, no container, no constructor.
 *
 * It exists so the traits are analysed against a real using class rather than in isolation, which
 * is the only way PHPStan checks a trait at all. It doubles as the worked example the docs point
 * at: this is exactly how much wiring a consumer needs.
 */
final class ChronoConsumer
{
    use InteractsWithChrono;

    public function currentTimeIn(mixed $zone): string
    {
        return $this->nowInZone($zone)->format('H:i');
    }

    public function bookingInstant(string $wallClock, mixed $zone): DateTimeImmutable
    {
        return $this->atLocal($wallClock, $zone);
    }

    public function bookingIsReal(string $wallClock, mixed $zone): bool
    {
        return $this->localTimeExists($wallClock, $zone);
    }

    /** @return array<string, ConvertedTime> */
    public function acrossOffices(DateTimeInterface $instant, mixed $zones): array
    {
        return $this->convertTime($instant)->to($zones)->keyed();
    }

    public function renderedFor(DateTimeInterface $instant, string $locale): string
    {
        return $this->formatDate($instant, NamedFormat::LongDate, $locale);
    }

    public function agoFor(DateTimeInterface $instant, string $locale): string
    {
        return $this->humanizeDate($instant, $locale);
    }

    /** @return list<string> */
    public function offeredZones(): array
    {
        return $this->zoneIdentifiers();
    }

    /** @return array<array-key, mixed> */
    public function pickerOptions(): array
    {
        return $this->zoneOptions();
    }

    public function stamp(): DateTimeImmutable
    {
        return $this->now();
    }
}
