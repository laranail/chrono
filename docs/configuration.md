# Configuration

Every key in `config/laranail/chrono.php`, in file order.

Publish it with `php artisan vendor:publish --tag=laranail::chrono-config`. Every key has a working
default; the package boots with none of them set. Keys are vendor-namespaced, so a value is read as
`config('laranail.chrono.default')`.

## Default and fallback zone

| Key | Env | Default |
|---|---|---|
| `default` | `CHRONO_DEFAULT` | `APP_TIMEZONE`, else `UTC` |
| `fallback` | `CHRONO_FALLBACK` | `UTC` |

`default` inherits `app.timezone` so the application keeps one source of truth. `fallback` is the
last resort when a value cannot be resolved at all, and is only consulted in lenient mode.

The package never calls `date_default_timezone_set()` and never rewrites `app.timezone`.

## Catalogue

| Key | Env | Default | Notes |
|---|---|---|---|
| `catalogue.include_deprecated` | `CHRONO_INCLUDE_DEPRECATED` | `false` | Adds the 128 aliases. With it on, a picker can offer the same zone twice |
| `catalogue.include_fixed` | `CHRONO_INCLUDE_FIXED` | `false` | Adds `Etc/*`. Their sign is inverted — `Etc/GMT+5` is UTC−05:00 |
| `catalogue.include_utc` | `CHRONO_INCLUDE_UTC` | `true` | |
| `catalogue.only` | — | `[]` | Absolute whitelist |
| `catalogue.except` | — | `[]` | |
| `catalogue.countries` | — | `[]` | ISO 3166-1 alpha-2; empty means all |
| `catalogue.sort` | `CHRONO_SORT` | `offset` | A `TimezoneField` value |

## Resolution

| Key | Env | Default | Notes |
|---|---|---|---|
| `resolution.strict` | `CHRONO_STRICT` | `true` | Unresolvable or genuinely ambiguous input throws rather than guesses |
| `resolution.canonicalise` | `CHRONO_CANONICALISE` | `true` | |
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

## Daylight saving

| Key | Env | Default |
|---|---|---|
| `dst.on_gap` | `CHRONO_DST_GAP` | `forward` |
| `dst.on_ambiguous` | `CHRONO_DST_AMBIGUOUS` | `earlier` |

The defaults reproduce PHP's own behaviour, so adopting the package changes nothing until you opt
in. Use `throw` for bookings, payroll and billing. See [Daylight saving](daylight-saving.md).

## Display

| Key | Env | Default |
|---|---|---|
| `display.offset_format` | `CHRONO_OFFSET_FORMAT` | `utc` — renders `UTC +03:00` |
| `display.datetime_format` | `CHRONO_DATETIME_FORMAT` | `M j, Y H:i` |
| `display.locale` | — | `null`, meaning `app()->getLocale()` |

`utc` reproduces the shape `simtabi/pheg` emitted, for a byte-identical migration. The other formats
are `colon`, `compact`, `short`, `gmt`, `iso8601` and `seconds`.

## Picker

| Key | Env | Default |
|---|---|---|
| `select.shape` | `CHRONO_SELECT_SHAPE` | `grouped` |
| `select.placeholder` | — | `null` |

Shapes are `flat`, `grouped`, `formed` and `payload`. See [Querying](tools/query.md#select-shapes).

## Cache

| Key | Env | Default |
|---|---|---|
| `cache.enabled` | `CHRONO_CACHE` | `true` |
| `cache.store` | `CHRONO_CACHE_STORE` | `null`, meaning the default store |
| `cache.prefix` | `CHRONO_CACHE_PREFIX` | `laranail.chrono` |
| `cache.ttl` | `CHRONO_CACHE_TTL` | `86400` |

Laravel's cache repository already implements PSR-16, so no adapter is involved. Keys embed a tzdata
fingerprint and self-invalidate; no manual flush is ever required after a PHP or OS upgrade.

## Health

| Key | Env | Default |
|---|---|---|
| `doctor.min_tzdata` | `CHRONO_TZDATA_MIN` | `2024.1` |
| `doctor.warn_on_icu_drift` | `CHRONO_ICU_DRIFT_WARN` | `true` |
| `doctor.strict` | `CHRONO_DOCTOR_STRICT` | `false` |

These keys are read by the health tooling arriving in `v0.2`; they are accepted and validated now so
configuration written today does not need revisiting.

---

[← Docs index](../README.md#documentation)
