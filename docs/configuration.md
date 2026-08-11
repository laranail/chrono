# Configuration

Every key in `config/laranail/chrono.php`, in file order.

Publish it with `php artisan vendor:publish --tag=laranail::chrono-config`. Every key has a working
default; the package boots with none of them set. Keys are vendor-namespaced, so a value is read as
`config('laranail.chrono.default')`.

Every key on this page is read by code, and a test enforces that. A setting that changes nothing is
worse than one that does not exist — it reads as a decision the application made and did not.
`ConfigWiringTest` flips each one and asserts the behaviour it promises, and one test in it walks the
whole file and fails on any key the source never mentions. That guard was written after an audit
found the entire `catalogue` section inert: the picker offered forty zones while validation accepted
all 419, and nothing surfaced the disagreement until a user submitted a zone the form never showed.

## Default and fallback zone

| Key | Env | Default |
|---|---|---|
| `default` | `CHRONO_DEFAULT` | `APP_TIMEZONE`, else `UTC` |
| `fallback` | `CHRONO_FALLBACK` | `UTC` |

`default` inherits `app.timezone` so the application keeps one source of truth. `fallback` is the
last resort when a value cannot be resolved at all, and is only consulted in lenient mode.

The package never calls `date_default_timezone_set()` and never rewrites `app.timezone`.

## Catalogue

Which zones this application offers. Applied to every query the service hands out — the picker, the
API and the validation rules — so they cannot disagree about the answer.

| Key | Env | Default | Notes |
|---|---|---|---|
| `catalogue.include_deprecated` | `CHRONO_INCLUDE_DEPRECATED` | `false` | Adds the 128 aliases. With it on, a picker can offer the same zone twice |
| `catalogue.include_fixed` | `CHRONO_INCLUDE_FIXED` | `false` | Adds `Etc/*`. Their sign is inverted — `Etc/GMT+5` is UTC−05:00 |
| `catalogue.include_utc` | `CHRONO_INCLUDE_UTC` | `true` | |
| `catalogue.only` | — | `[]` | Absolute whitelist |
| `catalogue.except` | — | `[]` | |
| `catalogue.countries` | — | `[]` | ISO 3166-1 alpha-2; empty means all |
| `catalogue.sort` | `CHRONO_SORT` | `offset` | A `TimezoneField` value. Prefix with `-` to reverse: `-offset` runs UTC+14 down to UTC−12 |

Restricting what you *offer* is not the same as refusing to understand a value that is already in a
database. Resolution is deliberately unaffected: with `only => ['UTC', 'Africa/Nairobi']`, a stored
`Antarctica/Troll` still resolves, still renders and still converts. Reach the unrestricted set
explicitly with `Timezones::unrestrictedQuery()`.

`chrono:doctor` fails outright when the configured catalogue matches no zones, because that is a
broken application rather than a stale one: every picker is blank and every rule rejects everything.

## Resolution

| Key | Env | Default | Notes |
|---|---|---|---|
| `resolution.strict` | `CHRONO_STRICT` | `true` | Unresolvable or genuinely ambiguous input throws rather than guesses |
| `resolution.canonicalise` | `CHRONO_CANONICALISE` | `true` | Rewrites a deprecated identifier to the one it points at |
| `resolution.abbreviations` | `CHRONO_RESOLVE_ABBREVIATIONS` | `false` | 96 of 144 abbreviations are ambiguous |
| `resolution.strategies` | — | all nine, in order | Order is load-bearing — see below |
| `resolution.preferred_countries` | — | `[]` | Breaks ties for abbreviations and city names |
| `resolution.country_defaults` | — | `[]` | e.g. `['US' => 'America/New_York']`. Nothing is guessed for you |
| `resolution.aliases` | — | `[]` | Merged over the generated map |

The default strategy order is `instance, identifier, alias, offset, windows, country, locale,
abbreviation, city`. `identifier` must precede `abbreviation` because `EST` is both a real
identifier and an abbreviation for 41 zones; `alias` must follow `identifier` so a deprecated name
is recognised before anything tries to read it as a place. Reordering those two is how
`Asia/Calcutta` and `Asia/Kolkata` end up in a database as separate zones.

Keep `strict` on anywhere you write to a database.

### Turning canonicalisation off

`canonicalise` on — the default — means `Asia/Calcutta` in gives `Asia/Kolkata` out, which is what
stops the same place accumulating two spellings across a table. Turn it off when you are migrating
gradually, or must echo back exactly what a third party sent:

```php
config(['laranail.chrono.resolution.canonicalise' => false]);

Timezones::resolve('Asia/Calcutta');                       // Asia/Calcutta
Timezones::of('Asia/Calcutta')->canonicalIdentifier();     // Asia/Kolkata — it still knows
Timezones::of('Asia/Calcutta')->equals('Asia/Kolkata');    // true — and still compares equal
```

Only an exact IANA alias is preserved. Anything else the chain understood — an abbreviation, a
country code, a Windows name — has no identity of its own to keep, so it still resolves normally.
The explicit `Timezones::canonicalise()` call is unaffected: that is what its name promises.

Per-instance rather than globally: `Timezones::preservingAliases()`.

## Daylight saving

| Key | Env | Default |
|---|---|---|
| `dst.on_gap` | `CHRONO_DST_GAP` | `forward` |
| `dst.on_ambiguous` | `CHRONO_DST_AMBIGUOUS` | `earlier` |

The setting the package exists for, and the one worth deciding deliberately. It reaches every call
site that interprets a wall-clock reading without naming a policy — `Timezone::at()`, the converter,
the validation rules — so `throw` does not have to be threaded through an application by hand:

```php
// config/laranail/chrono.php → 'dst' => ['on_gap' => 'throw', 'on_ambiguous' => 'throw']

Timezones::of('America/New_York')->at('2026-03-08 02:30');   // SkippedLocalTime
Chrono::convert('2026-11-01 01:30')->from('America/New_York')->to('UTC')->first();  // AmbiguousLocalTime
```

A call site may still decide for itself by passing a policy, which overrides the configured one for
that call only. The defaults reproduce PHP's own behaviour, so adopting the package changes nothing
until you opt in. Use `throw` for bookings, payroll and billing. See
[Daylight saving](daylight-saving.md).

`chrono:doctor` reports the pair in force and says so when it is the permissive one.

## Display

| Key | Env | Default |
|---|---|---|
| `display.offset_format` | `CHRONO_OFFSET_FORMAT` | `utc` — renders `UTC +03:00` |
| `display.datetime_format` | `CHRONO_DATETIME_FORMAT` | `M j, Y H:i` |
| `display.time_format` | `CHRONO_TIME_FORMAT` | `H:i` |
| `display.locale` | — | `null`, meaning `app()->getLocale()` |

One offset shape for the whole application. Without it the picker and the API each pick their own
default and the same zone renders as `UTC +03:00` in a form and `+03:00` in a JSON response of the
same product.

`datetime_format` is what a converted instant renders as; `time_format` is the clock a picker shows
beside each zone. Both are PHP `date()` patterns — for a locale-correct rendering use the
[Format module](tools/format.md) instead. `utc` reproduces the shape `simtabi/pheg` emitted, for a
byte-identical migration; the other offset formats are `colon`, `compact`, `short`, `gmt`, `iso8601`
and `seconds`.

Read the resolved set with `Chrono::display()`.

## Picker

| Key | Env | Default |
|---|---|---|
| `select.shape` | `CHRONO_SELECT_SHAPE` | `grouped` |
| `select.placeholder` | — | `null`, meaning `trans('chrono::messages.select.placeholder')` |

The shape sets grouping and label template together, so the Blade component and anything calling
`Chrono::present()->toShape()` agree without either restating it:

| Shape | Result |
|---|---|
| `flat` | `['Africa/Nairobi' => 'Nairobi, Kenya (UTC +03:00)']` |
| `grouped` | `['Africa' => ['Africa/Nairobi' => 'Nairobi (UTC +03:00)']]` |
| `formed` | `['Africa' => ['Africa/Nairobi' => 'Africa/Nairobi (UTC +03:00)']]` |
| `payload` | `[['id' => …, 'label' => …, 'search' => …, 'dir' => …], …]` |

One field can override the application default:

```blade
<x-chrono::timezone-select name="tz" shape="flat" />
```

See [the Blade component](tools/blade.md) and [Presentation](tools/presentation.md).

## Cache

| Key | Env | Default |
|---|---|---|
| `cache.enabled` | `CHRONO_CACHE` | `true` |
| `cache.store` | `CHRONO_CACHE_STORE` | `null`, meaning the default store |
| `cache.prefix` | `CHRONO_CACHE_PREFIX` | `laranail.chrono` |
| `cache.ttl` | `CHRONO_CACHE_TTL` | `86400` |

Laravel's cache repository already implements PSR-16, so no adapter is involved. Keys embed a tzdata
fingerprint and self-invalidate; no manual flush is ever required after a PHP or OS upgrade.

Only transition-derived data is cached, because measurement says the rest is not worth a round trip:
across all 419 zones, building every `DateTimeZone` takes 1.17 ms and reading every location 0.70 ms,
while reading full transition histories takes 22.8 ms.

## Health

| Key | Env | Default |
|---|---|---|
| `doctor.min_tzdata` | `CHRONO_TZDATA_MIN` | `2024.1` |
| `doctor.warn_on_icu_drift` | `CHRONO_ICU_DRIFT_WARN` | `true` |
| `doctor.strict` | `CHRONO_DOCTOR_STRICT` | `false` |

Read by `php artisan laranail::chrono.doctor`. `strict` turns warnings into a non-zero exit
permanently, which is what a CI pipeline wants; the `--strict` flag does the same for one run. A
genuine failure — `ext-intl` missing, an unresolvable `default`, an empty catalogue — always exits
non-zero regardless.

`min_tzdata` is compared against `timezone_version_get()`. Zones change several times a year by
government decree, so a host two years stale is quietly wrong about Egypt, Morocco, Iran and Chile.
`warn_on_icu_drift` reports when `ext-intl`'s bundled tz database disagrees with PHP's — they ship
separately and the gap can be years; a machine was observed running PHP tzdata 2025.3 against ICU
tzdata 2019a. Localised names come from ICU and offsets from PHP, so the two can disagree about the
same zone.

---

[← Docs index](../README.md#documentation)
