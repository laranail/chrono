<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Enums;

use DateTimeZone;

/**
 * The top-level segment of an IANA identifier.
 *
 * The masks are PHP's own `DateTimeZone` group constants and partition the 419 canonical zones
 * exactly. `Etc` is deliberately absent from `ALL`: those 35 zones exist only in `ALL_WITH_BC`,
 * which is why including them is a separate switch from including deprecated aliases.
 */
enum Region: string
{
    case Africa = 'Africa';
    case America = 'America';
    case Antarctica = 'Antarctica';
    case Arctic = 'Arctic';
    case Asia = 'Asia';
    case Atlantic = 'Atlantic';
    case Australia = 'Australia';
    case Europe = 'Europe';
    case Indian = 'Indian';
    case Pacific = 'Pacific';
    case Utc = 'UTC';
    case Etc = 'Etc';

    public function mask(): int
    {
        return match ($this) {
            self::Africa => DateTimeZone::AFRICA,
            self::America => DateTimeZone::AMERICA,
            self::Antarctica => DateTimeZone::ANTARCTICA,
            self::Arctic => DateTimeZone::ARCTIC,
            self::Asia => DateTimeZone::ASIA,
            self::Atlantic => DateTimeZone::ATLANTIC,
            self::Australia => DateTimeZone::AUSTRALIA,
            self::Europe => DateTimeZone::EUROPE,
            self::Indian => DateTimeZone::INDIAN,
            self::Pacific => DateTimeZone::PACIFIC,
            self::Utc, self::Etc => DateTimeZone::UTC,
        };
    }

    public static function fromIdentifier(string $identifier): ?self
    {
        $head = str_contains($identifier, '/') ? strstr($identifier, '/', true) : $identifier;

        return self::tryFrom((string) $head);
    }

    /** Regions that name a real place, so a picker can omit UTC and Etc from its groups. */
    public function isGeographic(): bool
    {
        return $this !== self::Utc && $this !== self::Etc;
    }
}
