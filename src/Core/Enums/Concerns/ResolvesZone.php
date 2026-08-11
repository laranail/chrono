<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Enums\Concerns;

use BackedEnum;
use DateTimeZone;
use Simtabi\Laranail\Chrono\Core\Enums\TimezoneKind;
use Simtabi\Laranail\Chrono\Core\Timezone\Support\AliasMap;
use Simtabi\Laranail\Chrono\Core\Timezone\Value\Timezone;

/**
 * Behaviour for the generated identifier enums.
 *
 * It lives here, hand-written and individually testable, rather than being emitted into the
 * generated files — which stay pure case lists so the byte-for-byte sync test means something.
 *
 * @mixin BackedEnum
 */
trait ResolvesZone
{
    public function toDateTimeZone(): DateTimeZone
    {
        return new DateTimeZone($this->value);
    }

    public function toTimezone(): Timezone
    {
        return new Timezone($this->value, $this->kind());
    }

    public function kind(): TimezoneKind
    {
        if (AliasMap::isAlias($this->value)) {
            return TimezoneKind::Link;
        }

        if ($this->value === 'UTC' || str_starts_with($this->value, 'Etc/')) {
            return TimezoneKind::Fixed;
        }

        return in_array($this->value, DateTimeZone::listIdentifiers(DateTimeZone::ALL), true)
            ? TimezoneKind::Canonical
            : TimezoneKind::Legacy;
    }

    /** The canonical identifier, following an alias where there is one. */
    public function canonical(): string
    {
        return AliasMap::canonical($this->value) ?? $this->value;
    }

    /** `Africa/Nairobi` -> `Nairobi`. */
    public function city(): string
    {
        $segment = str_contains($this->value, '/')
            ? substr((string) strrchr($this->value, '/'), 1)
            : $this->value;

        return str_replace('_', ' ', $segment);
    }
}
