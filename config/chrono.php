<?php

declare(strict_types=1);

use Simtabi\Laranail\Chrono\Core\Enums\AmbiguityPolicy;
use Simtabi\Laranail\Chrono\Core\Enums\GapPolicy;
use Simtabi\Laranail\Chrono\Core\Enums\OffsetFormat;
use Simtabi\Laranail\Chrono\Core\Enums\SelectShape;
use Simtabi\Laranail\Chrono\Core\Enums\TimezoneField;

return [

    /*
    |--------------------------------------------------------------------------
    | Default and fallback zone
    |--------------------------------------------------------------------------
    |
    | Read as `config('laranail.chrono.default')` — this file publishes to
    | `config/laranail/chrono.php`, matching the `laranail::chrono.<command>`
    | shape commands use.
    |
    | `default` is the zone treated as authoritative when no viewer preference is
    | known; it inherits `app.timezone` so the application keeps one source of
    | truth. `fallback` is the last resort when a value cannot be resolved at all.
    |
    | This package never calls date_default_timezone_set() and never rewrites
    | `app.timezone`. Laravel's storage contract assumes the process default
    | matches that value, so moving it would corrupt every stored timestamp.
    | Zones here are an input and presentation concern only.
    |
    */

    'default' => env('CHRONO_DEFAULT', env('APP_TIMEZONE', 'UTC')),

    'fallback' => env('CHRONO_FALLBACK', 'UTC'),

    /*
    |--------------------------------------------------------------------------
    | Catalogue
    |--------------------------------------------------------------------------
    |
    | Which of the 419 canonical identifiers your product actually offers.
    |
    | `include_deprecated` adds the 128 backward-compatible aliases such as
    | `Asia/Calcutta` and `US/Eastern`. Leave it off unless you must accept
    | legacy input: with it on, a picker can offer the same zone twice under two
    | names, which is exactly how a database ends up holding both spellings.
    |
    | `include_fixed` adds `Etc/*`. Note their sign is inverted by the POSIX
    | convention IANA inherited — `Etc/GMT+5` is UTC-05:00, not UTC+05:00.
    |
    */

    'catalogue' => [
        'include_deprecated' => env('CHRONO_INCLUDE_DEPRECATED', false),
        'include_fixed' => env('CHRONO_INCLUDE_FIXED', false),
        'include_utc' => env('CHRONO_INCLUDE_UTC', true),

        // Restrict to these identifiers entirely. Empty means "everything else applies".
        'only' => [],

        'except' => [],

        // ISO 3166-1 alpha-2 codes. Empty means every country.
        'countries' => [],

        // The order every picker, API response and rule list comes back in.
        // Prefix with `-` to reverse: `-offset` runs UTC+14 down to UTC-12.
        'sort' => env('CHRONO_SORT', TimezoneField::Offset->value),
    ],

    /*
    |--------------------------------------------------------------------------
    | Resolution
    |--------------------------------------------------------------------------
    |
    | How arbitrary input becomes a canonical identifier. Strategies run in the
    | listed order and the first confident answer wins; the order matters, since
    | `identifier` must precede `abbreviation` (`EST` is both a real identifier
    | and an abbreviation for 41 zones) and `alias` must follow `identifier`.
    |
    | `strict` makes an unresolvable or genuinely ambiguous value throw rather
    | than silently pick. Keep it on anywhere you write to a database.
    |
    | `abbreviations` is off by default because they are mostly ambiguous: 96 of
    | the 144 PHP knows map to more than one zone, and `CST` alone matches 62.
    | With it on, `preferred_countries` decides the winner.
    |
    */

    'resolution' => [
        'strict' => env('CHRONO_STRICT', true),
        'canonicalise' => env('CHRONO_CANONICALISE', true),
        'abbreviations' => env('CHRONO_RESOLVE_ABBREVIATIONS', false),

        'strategies' => [
            'instance', 'identifier', 'alias', 'offset',
            'windows', 'country', 'locale', 'abbreviation', 'city',
        ],

        'preferred_countries' => [],

        // Pin a multi-zone country to one identifier, e.g. 'US' => 'America/New_York'.
        // Nothing is guessed for you: the United States has 29 zones.
        'country_defaults' => [],

        // Extra alias => canonical pairs, merged over the generated map.
        'aliases' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Daylight saving
    |--------------------------------------------------------------------------
    |
    | Twice a year every observing zone produces a wall-clock reading that maps
    | to no instant, and one that maps to two:
    |
    |   GAP        2026-03-08 02:30 America/New_York never happened.
    |              PHP silently returns 03:30 EDT.
    |   AMBIGUOUS  2026-11-01 01:30 America/New_York happened twice.
    |              PHP silently picks one — and which one it picks differs
    |              between zones. Europe/London yields the later instant while
    |              America/New_York yields the earlier.
    |
    | `forward` and `earlier` are the defaults because they reproduce PHP's own
    | behaviour, so adopting this package changes nothing until you opt in.
    | `throw` is the right setting for bookings, payroll and billing.
    |
    */

    'dst' => [
        'on_gap' => env('CHRONO_DST_GAP', GapPolicy::Forward->value),
        'on_ambiguous' => env('CHRONO_DST_AMBIGUOUS', AmbiguityPolicy::Earlier->value),
    ],

    /*
    |--------------------------------------------------------------------------
    | Display
    |--------------------------------------------------------------------------
    |
    | `offset_format` renders a UTC offset:
    |   colon +03:00   compact +0300   short +3   gmt GMT+03:00
    |   utc UTC +03:00   iso8601 +03:00 (Z at zero)   seconds 10800
    |
    | `utc` reproduces the shape simtabi/pheg emitted, for a byte-identical
    | migration.
    |
    | `datetime_format` is what a converted time renders as; `time_format` is
    | the clock a picker shows beside each zone. Both are PHP `date()` patterns.
    | For a locale-correct rendering use the Format module instead — these are
    | the fixed shapes a form and an API want to agree on.
    |
    */

    'display' => [
        'offset_format' => env('CHRONO_OFFSET_FORMAT', OffsetFormat::Utc->value),
        'datetime_format' => env('CHRONO_DATETIME_FORMAT', 'M j, Y H:i'),
        'time_format' => env('CHRONO_TIME_FORMAT', 'H:i'),
        'locale' => null, // null = app()->getLocale()
    ],

    /*
    |--------------------------------------------------------------------------
    | Picker
    |--------------------------------------------------------------------------
    |
    | flat     ['Africa/Nairobi' => 'Nairobi, KE (UTC +03:00)']
    | grouped  ['Africa' => ['Africa/Nairobi' => 'Nairobi (UTC +03:00)']]
    | formed   ['Africa' => ['Africa/Nairobi' => 'Africa/Nairobi (UTC +03:00)']]
    | payload  [['id' => …, 'label' => …, 'search' => …, 'dir' => …], …]
    |
    | The shape sets both the grouping and the label template, so the Blade
    | component and anything calling `present()->toShape()` agree without either
    | restating it. `<x-chrono-timezone-select shape="flat" />` overrides it for
    | one field.
    |
    */

    'select' => [
        'shape' => env('CHRONO_SELECT_SHAPE', SelectShape::Grouped->value),
        'placeholder' => null, // null = trans('laranail-chrono::messages.select.placeholder')
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Only transition-derived data is worth caching. Measured across all 419
    | zones: constructing every DateTimeZone takes 1.17 ms, getOffset 0.96 ms and
    | getLocation 0.70 ms — all cheaper than a round trip to a cache server.
    | Reading full transition histories takes 22.8 ms, and that is what this
    | covers.
    |
    | Keys embed a fingerprint of PHP's tz database, so a tzdata upgrade rotates
    | the whole key space and no manual flush is ever required.
    |
    */

    'cache' => [
        'enabled' => env('CHRONO_CACHE', true),
        'store' => env('CHRONO_CACHE_STORE'), // null = default store
        'prefix' => env('CHRONO_CACHE_PREFIX', 'laranail.chrono'),
        'ttl' => (int) env('CHRONO_CACHE_TTL', 86400),
    ],

    /*
    |--------------------------------------------------------------------------
    | Health checks
    |--------------------------------------------------------------------------
    |
    | `min_tzdata` is compared against timezone_version_get(). Zones change
    | several times a year by government decree, so a host two years stale is
    | quietly wrong about Egypt, Morocco, Iran and Chile.
    |
    | `warn_on_icu_drift` reports when ext-intl's bundled tz database disagrees
    | with PHP's — they ship separately and the gap can be years. A machine was
    | observed running PHP tzdata 2025.3 against ICU tzdata 2019a.
    |
    */

    'doctor' => [
        'min_tzdata' => env('CHRONO_TZDATA_MIN', '2024.1'),
        'warn_on_icu_drift' => env('CHRONO_ICU_DRIFT_WARN', true),
        'strict' => env('CHRONO_DOCTOR_STRICT', false),
    ],

];
