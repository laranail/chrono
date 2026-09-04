<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Casts;

use Illuminate\Database\Eloquent\Model;
use Simtabi\Laranail\Chrono\Core\Timezone\Timezones;
use Simtabi\Laranail\Chrono\Core\Timezone\Value\Timezone;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Simtabi\Laranail\Chrono\Core\Exception\TimezoneNotFound;

/**
 * Casts a column to a `Timezone`, canonicalising on the way in.
 *
 * The write side is the point. This package's resolver deliberately *accepts* deprecated aliases —
 * that is what lets `US/Eastern` from a legacy integration resolve at all — so without
 * canonicalisation a column would end up holding both `Asia/Calcutta` and `Asia/Kolkata`, and the
 * application would treat them as different zones. This rewrites to the canonical form before the
 * value reaches the database.
 *
 * Note Laravel's own `timezone` rule validates against `DateTimeZone::ALL` and so rejects aliases
 * outright. That is a different, blunter answer to the same problem: it refuses the input rather
 * than accepting and normalising it.
 *
 * @implements CastsAttributes<Timezone, Timezone|string|null>
 */
final readonly class AsTimezone implements CastsAttributes
{
    public function __construct(private bool $canonicalise = true) {}

    /** Opt out of rewriting, for a column that must preserve exactly what was submitted. */
    public static function verbatim(): string
    {
        return self::class . ':0';
    }

    public function get(Model $model, string $key, mixed $value, array $attributes): ?Timezone
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->timezones()->tryOf((string) $value);
    }

    /** @throws TimezoneNotFound when the value names no zone */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $identifier = $value instanceof Timezone
            ? $value->identifier
            : $this->timezones()->resolve($value);

        return $this->canonicalise
            ? $this->timezones()->canonicalise($identifier)
            : $identifier;
    }

    private function timezones(): Timezones
    {
        /** @var Timezones $timezones */
        $timezones = app(Timezones::class);

        return $timezones;
    }
}
