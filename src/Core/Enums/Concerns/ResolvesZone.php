<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Enums\Concerns;

use BackedEnum;
use DateTimeZone;
use Simtabi\Laranail\Chrono\Core\Enums\TimezoneKind;
use Simtabi\Laranail\Chrono\Core\Timezone\Value\Timezone;
use Simtabi\Laranail\Chrono\Core\Timezone\Support\AliasMap;

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

    /**
     * A bare value object, with the engine's default daylight-saving policies.
     *
     * Deliberately not the application's configured pair: this layer has no framework and no
     * container to read one from. Where the configured policy matters — anywhere a wall-clock
     * reading is interpreted — go through the service instead, which accepts the enum directly:
     * `Timezones::of(Timezone::AmericaNewYork)`.
     */
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

        // Built once per process, as a hash. A linear scan of the 419-entry list would turn
        // building one collection into 175,000 string comparisons. It is a function static rather
        // than a class property because an enum may not declare properties.
        /** @var array<string, true>|null $canonical */
        static $canonical = null;

        $canonical ??= array_fill_keys(DateTimeZone::listIdentifiers(DateTimeZone::ALL), true);

        return isset($canonical[$this->value])
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
