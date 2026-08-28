<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Timezone\Value;

use DateTimeZone;
use JsonSerializable;

/**
 * Where a zone's reference city is.
 *
 * `DateTimeZone::getLocation()` also returns a `country_code`, which php.net does not document but
 * which is populated for 418 of the 419 canonical zones — only `UTC` is not. That makes
 * timezone-to-country a single call rather than a sweep over 249 country codes, and it is why this
 * package needs no bundled country dataset.
 *
 * The uneven cases are handled in `fromDateTimeZone()`: `GMT` returns `false` outright, and `UTC`
 * and every `Etc/*` zone return a sentinel of country `??` at latitude −90, longitude −180. Both
 * become null rather than a location at the South Pole.
 */
final readonly class Location implements JsonSerializable
{
    public function __construct(
        public float $latitude,
        public float $longitude,
        public ?string $countryCode,
        public string $comments = '',
    ) {}

    public static function fromDateTimeZone(DateTimeZone $zone): ?self
    {
        $location = $zone->getLocation();

        if ($location === false) {
            return null;
        }

        $country = $location['country_code'];
        $latitude = $location['latitude'];
        $longitude = $location['longitude'];

        // A zone with no country is not a place, and that is the only test that holds across builds.
        // The coordinates used to be checked too, against the bundled database's `-90/-180`
        // sentinel — but a system-tzdata build writes `0.0/0.0` with a `?` comment instead, so the
        // pair check quietly passed rule-less zones through as a location in the Gulf of Guinea.
        if ($country === '??' || $country === '') {
            return null;
        }

        return new self(
            latitude: $latitude,
            longitude: $longitude,
            countryCode: $country === '??' ? null : $country,
            comments: $location['comments'],
        );
    }

    /** Great-circle distance in kilometres. Reference cities are points, so this is approximate. */
    public function distanceTo(self $other): float
    {
        $earthRadius = 6371.0088;

        $latitudeDelta = deg2rad($other->latitude - $this->latitude);
        $longitudeDelta = deg2rad($other->longitude - $this->longitude);

        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($this->latitude)) * cos(deg2rad($other->latitude)) * sin($longitudeDelta / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /** @return array{latitude: float, longitude: float, country_code: string|null, comments: string} */
    public function toArray(): array
    {
        return [
            'latitude'     => $this->latitude,
            'longitude'    => $this->longitude,
            'country_code' => $this->countryCode,
            'comments'     => $this->comments,
        ];
    }

    /** @return array{latitude: float, longitude: float, country_code: string|null, comments: string} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
