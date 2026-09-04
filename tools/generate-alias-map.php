<?php

declare(strict_types=1);

/**
 * Generates src/Core/Timezone/Support/AliasMap.php — deprecated identifier => canonical identifier.
 *
 * This cannot be read out of PHP or ICU. Both are unhelpful in the same way:
 * `new DateTimeZone('Asia/Calcutta')->getName()` returns `Asia/Calcutta`, and
 * `IntlTimeZone::getCanonicalID('Asia/Calcutta')` also returns `Asia/Calcutta` (verified on ICU
 * 57.2). `IntlTimeZone::getIanaID()` fatals on older ICU builds.
 *
 * So the map is derived, then curated where derivation is genuinely ambiguous:
 *
 *   1. Fingerprint every canonical zone by its 1970-2038 transition history. An alias is an exact
 *      link, so its target must share that fingerprint byte for byte.
 *   2. Where several canonical zones share the fingerprint (they often do — 12 collision groups
 *      exist among the canonical set alone), narrow by the country code both carry, then by
 *      identical coordinates.
 *   3. Where that still leaves more than one — 60 of the 179 aliases — take the curated answer
 *      below, which follows the IANA `backward` file.
 *
 * Every emitted pair is then re-validated against rule 1, so a curated entry that is simply wrong
 * fails the build rather than shipping.
 *
 * Zones with no canonical target at all are not aliases and are excluded: `Etc/*` are fixed offsets,
 * and `CET`, `EET`, `EST`, `MET`, `MST`, `HST`, `WET`, `EST5EDT`, `CST6CDT`, `MST7MDT`, `PST8PDT`
 * carry their own rules. They are classified `TimezoneKind::Fixed` and `Legacy` instead.
 *
 * Usage:  php tools/generate-alias-map.php [--check]
 */

/** Aliases whose target cannot be distinguished by rules, country and coordinates alone. */
const CURATED = [
    'Africa/Asmera'                    => 'Africa/Asmara',
    'Africa/Timbuktu'                  => 'Africa/Bamako',
    'America/Argentina/ComodRivadavia' => 'America/Argentina/Catamarca',
    'America/Atka'                     => 'America/Adak',
    'America/Coral_Harbour'            => 'America/Atikokan',
    'America/Ensenada'                 => 'America/Tijuana',
    'America/Fort_Wayne'               => 'America/Indiana/Indianapolis',
    'America/Montreal'                 => 'America/Toronto',
    'America/Nipigon'                  => 'America/Toronto',
    'America/Pangnirtung'              => 'America/Iqaluit',
    'America/Rainy_River'              => 'America/Winnipeg',
    'America/Rosario'                  => 'America/Argentina/Cordoba',
    'America/Santa_Isabel'             => 'America/Tijuana',
    'America/Shiprock'                 => 'America/Denver',
    'America/Thunder_Bay'              => 'America/Toronto',
    'America/Virgin'                   => 'America/Puerto_Rico',
    'America/Yellowknife'              => 'America/Edmonton',
    'Antarctica/South_Pole'            => 'Antarctica/McMurdo',
    'Arctic/Longyearbyen'              => 'Europe/Oslo',
    'Asia/Chongqing'                   => 'Asia/Shanghai',
    'Asia/Chungking'                   => 'Asia/Shanghai',
    'Asia/Harbin'                      => 'Asia/Shanghai',
    'Asia/Istanbul'                    => 'Europe/Istanbul',
    'Asia/Kashgar'                     => 'Asia/Urumqi',
    'Asia/Rangoon'                     => 'Asia/Yangon',
    'Asia/Tel_Aviv'                    => 'Asia/Jerusalem',
    'Atlantic/Jan_Mayen'               => 'Europe/Oslo',
    'Australia/ACT'                    => 'Australia/Sydney',
    'Australia/Canberra'               => 'Australia/Sydney',
    'Australia/LHI'                    => 'Australia/Lord_Howe',
    'Australia/NSW'                    => 'Australia/Sydney',
    'Australia/North'                  => 'Australia/Darwin',
    'Australia/Queensland'             => 'Australia/Brisbane',
    'Australia/South'                  => 'Australia/Adelaide',
    'Australia/Tasmania'               => 'Australia/Hobart',
    'Australia/Victoria'               => 'Australia/Melbourne',
    'Australia/West'                   => 'Australia/Perth',
    'Australia/Yancowinna'             => 'Australia/Broken_Hill',
    'Brazil/Acre'                      => 'America/Rio_Branco',
    'Brazil/DeNoronha'                 => 'America/Noronha',
    'Brazil/East'                      => 'America/Sao_Paulo',
    'Brazil/West'                      => 'America/Manaus',
    'Canada/Atlantic'                  => 'America/Halifax',
    'Canada/Central'                   => 'America/Winnipeg',
    'Canada/Eastern'                   => 'America/Toronto',
    'Canada/Mountain'                  => 'America/Edmonton',
    'Canada/Newfoundland'              => 'America/St_Johns',
    'Canada/Pacific'                   => 'America/Vancouver',
    'Canada/Saskatchewan'              => 'America/Regina',
    'Canada/Yukon'                     => 'America/Whitehorse',
    'Chile/Continental'                => 'America/Santiago',
    'Chile/EasterIsland'               => 'Pacific/Easter',
    'Cuba'                             => 'America/Havana',
    'Egypt'                            => 'Africa/Cairo',
    'Eire'                             => 'Europe/Dublin',
    'Europe/Belfast'                   => 'Europe/London',
    'Europe/Nicosia'                   => 'Asia/Nicosia',
    'Europe/Tiraspol'                  => 'Europe/Chisinau',
    'Europe/Uzhgorod'                  => 'Europe/Kyiv',
    'Europe/Zaporozhye'                => 'Europe/Kyiv',
    'GB'                               => 'Europe/London',
    'GB-Eire'                          => 'Europe/London',
    'GMT0'                             => 'UTC',
    'Greenwich'                        => 'UTC',
    'Hongkong'                         => 'Asia/Hong_Kong',
    'Iceland'                          => 'Atlantic/Reykjavik',
    'Iran'                             => 'Asia/Tehran',
    'Israel'                           => 'Asia/Jerusalem',
    'Jamaica'                          => 'America/Jamaica',
    'Japan'                            => 'Asia/Tokyo',
    'Kwajalein'                        => 'Pacific/Kwajalein',
    'Libya'                            => 'Africa/Tripoli',
    'Mexico/BajaNorte'                 => 'America/Tijuana',
    'Mexico/BajaSur'                   => 'America/Mazatlan',
    'Mexico/General'                   => 'America/Mexico_City',
    'NZ'                               => 'Pacific/Auckland',
    'NZ-CHAT'                          => 'Pacific/Chatham',
    'Navajo'                           => 'America/Denver',
    'PRC'                              => 'Asia/Shanghai',
    'Pacific/Johnston'                 => 'Pacific/Honolulu',
    'Pacific/Ponape'                   => 'Pacific/Pohnpei',
    'Pacific/Samoa'                    => 'Pacific/Pago_Pago',
    'Pacific/Truk'                     => 'Pacific/Chuuk',
    'Pacific/Yap'                      => 'Pacific/Chuuk',
    'Poland'                           => 'Europe/Warsaw',
    'Portugal'                         => 'Europe/Lisbon',
    'ROC'                              => 'Asia/Taipei',
    'ROK'                              => 'Asia/Seoul',
    'Singapore'                        => 'Asia/Singapore',
    'Turkey'                           => 'Europe/Istanbul',
    'US/Alaska'                        => 'America/Anchorage',
    'US/Aleutian'                      => 'America/Adak',
    'US/Arizona'                       => 'America/Phoenix',
    'US/Central'                       => 'America/Chicago',
    'US/East-Indiana'                  => 'America/Indiana/Indianapolis',
    'US/Eastern'                       => 'America/New_York',
    'US/Hawaii'                        => 'Pacific/Honolulu',
    'US/Indiana-Starke'                => 'America/Indiana/Knox',
    'US/Michigan'                      => 'America/Detroit',
    'US/Mountain'                      => 'America/Denver',
    'US/Pacific'                       => 'America/Los_Angeles',
    'US/Samoa'                         => 'Pacific/Pago_Pago',
    'Universal'                        => 'UTC',
    'W-SU'                             => 'Europe/Moscow',
    'Zulu'                             => 'UTC',
];

/**
 * Not aliases: these carry their own rules or are fixed offsets, with no canonical target.
 *
 * The `GMT` group is easy to mistake for an alias of `UTC` and is not. Verified: `GMT` and `UCT`
 * are abbreviation zones and `GMT+0` / `GMT-0` are offset zones whose `getName()` normalises to
 * `+00:00`. All four return `false` from both `getTransitions()` and `getLocation()`. The
 * region-style spellings — `GMT0`, `Greenwich`, `Universal`, `Zulu` — do behave like zones and are
 * mapped to `UTC` normally.
 */
const NOT_ALIASES = [
    'CET', 'CST6CDT', 'EET', 'EST', 'EST5EDT', 'Factory', 'GMT', 'GMT+0', 'GMT-0', 'HST', 'MET',
    'MST', 'MST7MDT', 'PST8PDT', 'UCT', 'WET', 'localtime',
];

require_once __DIR__ . '/pint-format.php';

$check = in_array('--check', $argv, true);
$target = dirname(__DIR__) . '/src/Core/Timezone/Support/AliasMap.php';

$canonical = DateTimeZone::listIdentifiers(DateTimeZone::ALL);
$withBc = DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC);
$aliases = array_values(array_diff($withBc, $canonical));

/**
 * Two fingerprints, deliberately.
 *
 * `strict` includes the abbreviation and is used to *narrow* candidates, because it separates zones
 * that behave identically but are named differently.
 *
 * `rules` omits it and is used to *validate*, because an alias is defined by sharing its target's
 * rules, not its label. `GMT` and `UTC` are the same instant forever and IANA lists one as a link to
 * the other, yet they report different abbreviations — validating on the strict fingerprint would
 * reject a pair that is correct by definition.
 */
$fingerprint = static function (string $identifier, bool $strict = true): string {
    // `listIdentifiers()` on a system-tzdata build reports files as well as zones — `tzdata.zi`,
    // `leapseconds` — and constructing one of those is a fatal error rather than an exception to
    // step over. This generator ran fine for months on a bundled-database machine and died on the
    // first Debian one it met.
    try {
        $zone = new DateTimeZone($identifier);
    } catch (Exception) {
        return 'none';
    }

    $transitions = $zone->getTransitions(0, 2145916800);

    if ($transitions === false) {
        return 'none';
    }

    return hash('xxh128', implode(',', array_map(
        static fn (array $t): string => $t['ts'] . ':' . $t['offset'] . ':' . (int) $t['isdst']
            . ($strict ? ':' . $t['abbr'] : ''),
        $transitions,
    )));
};

$location = static function (string $identifier): array {
    $raw = (new DateTimeZone($identifier))->getLocation();

    return $raw === false
        ? ['country_code' => '??', 'latitude' => 0.0, 'longitude' => 0.0]
        : $raw;
};

$index = [];

foreach ($canonical as $identifier) {
    $index[$fingerprint($identifier)][] = $identifier;
}

$map = [];
$unresolved = [];

foreach ($aliases as $alias) {
    // Etc/* are fixed offsets with no canonical target — and note Etc/GMT+5 is UTC-05:00, the sign
    // being inverted by the POSIX convention IANA inherited.
    if (in_array($alias, NOT_ALIASES, true) || str_starts_with($alias, 'Etc/')) {
        continue;
    }

    $candidates = $index[$fingerprint($alias)] ?? [];

    if (count($candidates) === 1) {
        $map[$alias] = $candidates[0];

        continue;
    }

    if ($candidates !== []) {
        $aliasLocation = $location($alias);

        $byCountry = array_values(array_filter(
            $candidates,
            static fn (string $c): bool => $location($c)['country_code'] === $aliasLocation['country_code'],
        ));

        if (count($byCountry) > 1) {
            $byGeo = array_values(array_filter($byCountry, static function (string $c) use ($location, $aliasLocation): bool {
                $candidateLocation = $location($c);

                return abs($candidateLocation['latitude'] - $aliasLocation['latitude']) < 0.001
                    && abs($candidateLocation['longitude'] - $aliasLocation['longitude']) < 0.001;
            }));

            if ($byGeo !== []) {
                $byCountry = $byGeo;
            }
        }

        if (count($byCountry) === 1) {
            $map[$alias] = $byCountry[0];

            continue;
        }
    }

    if (isset(CURATED[$alias])) {
        $map[$alias] = CURATED[$alias];

        continue;
    }

    $unresolved[] = $alias . ' (candidates: ' . (implode(' | ', $candidates) ?: 'none') . ')';
}

// Every pair must survive the rule that defines an alias: identical transition history.
$invalid = [];

foreach ($map as $alias => $target_) {
    if (! in_array($target_, $canonical, true)) {
        $invalid[] = "{$alias} -> {$target_} (target is not a canonical identifier)";

        continue;
    }

    if ($fingerprint($alias, strict: false) !== $fingerprint($target_, strict: false)) {
        $invalid[] = "{$alias} -> {$target_} (transition rules differ)";
    }
}

if ($unresolved !== [] || $invalid !== []) {
    fwrite(STDERR, "Alias map could not be generated.\n\n");

    foreach ($unresolved as $line) {
        fwrite(STDERR, "  unresolved: {$line}\n");
    }

    foreach ($invalid as $line) {
        fwrite(STDERR, "  invalid:    {$line}\n");
    }

    exit(1);
}

ksort($map);

$entries = '';

foreach ($map as $alias => $target_) {
    $entries .= sprintf("        %s => %s,\n", var_export($alias, true), var_export($target_, true));
}

$tzdataVersion = timezone_version_get();

$contents = <<<PHP
<?php

declare(strict_types=1);

namespace Simtabi\\Laranail\\Chrono\\Core\\Timezone\\Support;

/**
 * GENERATED by tools/generate-alias-map.php — do not edit by hand.
 *
 * Deprecated IANA identifier => canonical identifier. Regenerate with `laranail::chrono.sync`;
 * a test asserts this file matches what the generator emits against the runner's tz database, and
 * that every pair still shares a transition history.
 *
 * Built against tzdata {$tzdataVersion}.
 */
final class AliasMap
{
    /** @var array<string, string> */
    private const array MAP = [
{$entries}    ];

    /** @return array<string, string> */
    public static function all(): array
    {
        return self::MAP;
    }

    public static function canonical(string \$identifier): ?string
    {
        return self::MAP[\$identifier] ?? null;
    }

    public static function isAlias(string \$identifier): bool
    {
        return isset(self::MAP[\$identifier]);
    }

    public static function count(): int
    {
        return count(self::MAP);
    }
}

PHP;

$contents = laranail_pint_format($contents);

if ($check) {
    $existing = is_file($target) ? file_get_contents($target) : '';

    if ($existing !== $contents) {
        fwrite(STDERR, "AliasMap.php is out of sync with the runner's tz database. Run `laranail::chrono.sync`.\n");

        exit(1);
    }

    fwrite(STDOUT, sprintf("AliasMap.php is in sync (%d aliases).\n", count($map)));

    exit(0);
}

file_put_contents($target, $contents);

// The release every generated file in this repository corresponds to.
//
// Committed rather than inferred, because "is this data current?" and "can this host even tell?"
// are different questions and only the first one matters. A checker that compares against whatever
// database the runner happens to carry answers the second and calls it the first.
file_put_contents(
    dirname(__DIR__) . '/resources/tzdata-version.txt',
    timezone_version_get() . "\n",
);

fwrite(STDOUT, sprintf(
    "Wrote %s — %d aliases, %d excluded as non-aliases, tzdata %s.\n",
    $target,
    count($map),
    count(NOT_ALIASES),
    timezone_version_get(),
));
