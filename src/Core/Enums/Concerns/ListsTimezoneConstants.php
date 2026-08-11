<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Enums\Concerns;

use ReflectionClass;
use Simtabi\Laranail\Chrono\Core\Enums\Timezone;

/**
 * Behaviour for the generated `Tz` constants class.
 *
 * Hand-written and kept out of the generated file, so the emitted text stays a pure function of the
 * tz database and the byte-for-byte sync check keeps meaning something.
 *
 * Labels are derived rather than stored. Attaching a `#[Label]` to each of 419 constants would add
 * four hundred lines whose content is mechanically derivable from the value, and would freeze an
 * English label into a generated file — where a derived one can be localised, which is what
 * `Timezone::toTimezone()` and the formatter do.
 */
trait ListsTimezoneConstants
{
    /** @var array<string, string>|null */
    private static ?array $resolved = null;

    /**
     * Every constant, keyed by its name.
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        /** @var array<string, string> $constants */
        $constants = new ReflectionClass(static::class)->getConstants();

        return self::$resolved ??= $constants;
    }

    /** @return list<string> every identifier, in declaration order */
    public static function identifiers(): array
    {
        return array_values(self::all());
    }

    /** @return list<string> every constant name */
    public static function names(): array
    {
        return array_keys(self::all());
    }

    public static function has(string $identifier): bool
    {
        return in_array($identifier, self::all(), true);
    }

    /** `Africa/Nairobi` -> `AFRICA_NAIROBI`, or null when the identifier is not canonical. */
    public static function nameOf(string $identifier): ?string
    {
        $name = array_search($identifier, self::all(), true);

        return $name === false ? null : $name;
    }

    /** A human label — `Africa/Nairobi` -> `Nairobi`. Derived, so it can be localised downstream. */
    public static function label(string $identifier): string
    {
        $segment = str_contains($identifier, '/')
            ? substr((string) strrchr($identifier, '/'), 1)
            : $identifier;

        return str_replace('_', ' ', $segment);
    }

    /** The behaviour-carrying enum case for an identifier. */
    public static function enum(string $identifier): ?Timezone
    {
        return Timezone::tryFrom($identifier);
    }

    /**
     * `identifier => label`, ready for a `<select>` or an `in:` validation rule.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::identifiers() as $identifier) {
            $options[$identifier] = self::label($identifier);
        }

        return $options;
    }

    public static function count(): int
    {
        return count(self::all());
    }
}
