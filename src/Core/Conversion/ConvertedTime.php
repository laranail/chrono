<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Conversion;

use Stringable;
use JsonSerializable;
use DateTimeImmutable;
use Simtabi\Laranail\Chrono\Core\Enums\OffsetFormat;
use Simtabi\Laranail\Chrono\Core\Timezone\Value\Timezone;

/**
 * One instant, expressed in one zone.
 *
 * Carries the source instant alongside the local reading, because the two together are the answer:
 * "09:00 in Nairobi" and "06:00 in London" are the same moment, and a result that shows only the
 * local reading has thrown away the fact that makes it useful.
 */
final readonly class ConvertedTime implements JsonSerializable, Stringable
{
    public function __construct(
        public int $index,
        public DateTimeImmutable $instant,
        public Timezone $zone,
        public DateTimeImmutable $local,
        private string $format = 'Y-m-d H:i',
        private OffsetFormat $offsetFormat = OffsetFormat::Utc,
    ) {}

    public function __toString(): string
    {
        return $this->formatted();
    }

    public function formatted(): string
    {
        return $this->local->format($this->format);
    }

    public function offsetLabel(): string
    {
        return $this->zone->offset($this->instant)->format($this->offsetFormat);
    }

    public function abbreviation(): string
    {
        return $this->zone->abbreviation($this->instant);
    }

    public function isDst(): bool
    {
        return $this->zone->isDst($this->instant);
    }

    /** How far this zone is from another at this instant — the "they are 7 hours behind" number. */
    public function offsetFrom(self $other): int
    {
        return $this->zone->offset($this->instant)->seconds
            - $other->zone->offset($other->instant)->seconds;
    }

    /** @return array<string, scalar|null> */
    public function toArray(): array
    {
        return [
            'index'        => $this->index,
            'zone'         => $this->zone->identifier,
            'instant'      => $this->instant->format('c'),
            'local'        => $this->local->format('c'),
            'formatted'    => $this->formatted(),
            'offset'       => $this->zone->offset($this->instant)->seconds,
            'offset_label' => $this->offsetLabel(),
            'abbreviation' => $this->abbreviation(),
            'dst'          => $this->isDst(),
        ];
    }

    /** @return array<string, scalar|null> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
