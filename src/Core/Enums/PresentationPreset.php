<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Enums;

/**
 * A named field set, so the common cases need no field list at all.
 *
 * Every preset is a starting point, not a constraint: `->with()` and `->without()` adjust any of
 * them, and `->only()` discards the preset entirely.
 */
enum PresentationPreset: string
{
    /** `id`, `label`. Everything a `<select>` needs and nothing it does not. */
    case Select = 'select';

    /** Adds `value`-style keys and text direction for a JS form component. */
    case Form = 'form';

    /** The fields a client is likely to filter, sort or badge on. */
    case Api = 'api';

    /** Every field, including the ones that cost a transition scan. */
    case Full = 'full';

    /** `id` only — for validation lists and `in:` rules. */
    case Minimal = 'minimal';

    /** @return list<ZoneField> */
    public function fields(): array
    {
        return match ($this) {
            self::Minimal => [ZoneField::Id],
            self::Select => [ZoneField::Id, ZoneField::Label],
            self::Form => [
                ZoneField::Id, ZoneField::Label, ZoneField::Continent, ZoneField::Country,
                ZoneField::OffsetLabel, ZoneField::Flag, ZoneField::Search, ZoneField::Dir,
            ],
            self::Api => [
                ZoneField::Id, ZoneField::Label, ZoneField::City, ZoneField::Country,
                ZoneField::CountryName, ZoneField::Continent, ZoneField::Offset,
                ZoneField::OffsetLabel, ZoneField::Abbreviation, ZoneField::Dst,
                ZoneField::LocalTime, ZoneField::Search,
            ],
            self::Full => ZoneField::cases(),
        };
    }
}
