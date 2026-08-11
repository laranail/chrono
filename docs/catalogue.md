# The catalogue

What counts as a timezone identifier, and the four kinds you will meet.

## Four kinds

PHP exposes 419 canonical identifiers and 598 including backward-compatible ones. The 179-strong
difference is not one thing.

| Kind | Example | Has rules | Has a location | Canonical target |
|---|---|:---:|:---:|---|
| `Canonical` | `Africa/Nairobi` | yes | yes | itself |
| `Link` | `Asia/Calcutta`, `US/Eastern` | yes | yes | another identifier |
| `Fixed` | `UTC`, `Etc/GMT+5`, `+03:00` | no | no | none |
| `Legacy` | `CET`, `EST`, `MST7MDT` | yes | no | none |

Only `Canonical` zones are offered by a query unless you ask otherwise. That is what stops a picker
listing `Asia/Calcutta` and `Asia/Kolkata` as two separate options — a bug that shipped in this
estate's own hand-maintained JavaScript list.

## Aliases

```php
Timezones::canonicalise('US/Eastern');  // 'America/New_York'
Timezones::of('Asia/Calcutta');         // a Timezone whose identifier is 'Asia/Kolkata'
```

The alias map cannot be read out of PHP or ICU. `new DateTimeZone('Asia/Calcutta')->getName()`
returns the alias unchanged, and `IntlTimeZone::getCanonicalID('Asia/Calcutta')` returns
`'Asia/Calcutta'` too. So the map is generated: candidates are found by comparing transition
histories, narrowed by country and coordinates, and the remainder is curated from the IANA
`backward` file. Every pair is then re-validated against the rule that defines an alias — identical
rules — so a wrong entry fails the build rather than shipping.

128 aliases survive that process. The rest of the 179 are `Etc/*` and the legacy abbreviation zones,
which have no canonical target at all.

## Things that look like aliases and are not

`GMT`, `GMT+0`, `GMT-0` and `UCT` are commonly assumed to be aliases of `UTC`. They are not:
`GMT` and `UCT` are abbreviation zones, `GMT+0` and `GMT-0` are offset zones whose name normalises to
`+00:00`, and all four return `false` from both `getTransitions()` and `getLocation()`. The
region-style spellings — `GMT0`, `Greenwich`, `Universal`, `Zulu` — do behave like zones and are
mapped to `UTC` normally.

## Countries

```php
Timezones::inCountry('KE');            // TimezoneCollection
Timezones::of('Africa/Nairobi')->countryCode();  // 'KE'
```

`DateTimeZone::getLocation()` returns a `country_code` that php.net does not document but that is
populated for 418 of the 419 canonical zones — only `UTC` is not. Building the reverse index from it
is a single pass over the catalogue, which is why this package bundles no country dataset.

## Abbreviations

PHP knows 144, and **96 of them map to more than one zone** — `CST` matches 62, `EST` 41. An
abbreviation is a display label, never an identifier, and storing one has already lost information.

```php
use Simtabi\Laranail\Chrono\Core\Enums\TimezoneAbbreviation;

TimezoneAbbreviation::from('CST')->isAmbiguous();   // true
TimezoneAbbreviation::from('CST')->identifiers();   // 62 of them
TimezoneAbbreviation::from('EAT')->offsetSeconds(); // 10800 — unambiguous
```

Resolution from an abbreviation is therefore off by default. See
[Resolution](tools/resolution.md#abbreviations).

## Offsets

An offset is not a place. Resolving `+03:00` gives a fixed-offset zone rather than a city that
happens to share the offset today, because a city's offset is a fact about a date.

Offsets are validated to ±18 hours rather than the ±14 you might expect from modern extremes:
historical local mean times in the database reach −15:56:08 and +15:13:42, and rejecting them would
mean rejecting real data.

---

[← Docs index](../README.md#documentation)
