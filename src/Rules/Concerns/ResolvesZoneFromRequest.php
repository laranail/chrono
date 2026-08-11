<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Rules\Concerns;

use Illuminate\Support\Arr;
use Simtabi\Laranail\Chrono\Core\Timezone\Timezones;
use Simtabi\Laranail\Chrono\Core\Timezone\Value\Timezone;

/**
 * Reads the zone a cross-field rule should validate against.
 *
 * The zone comes from another field of the same request — a booking form asks for a start time and
 * a venue timezone together, and neither means anything without the other. Falls back to the
 * configured default when no field is named, so the rules stay usable on a single-timezone
 * application.
 */
trait ResolvesZoneFromRequest
{
    /** @var array<string, mixed> */
    protected array $data = [];

    /** @param array<string, mixed> $data */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    protected function zone(?string $field): ?Timezone
    {
        /** @var Timezones $timezones */
        $timezones = app(Timezones::class);

        if ($field === null) {
            return $timezones->tryOf((string) config('laranail.chrono.default', 'UTC'));
        }

        $value = Arr::get($this->data, $field);

        return is_string($value) && $value !== '' ? $timezones->tryOf($value) : null;
    }
}
