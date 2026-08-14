# Installation

Requirements, install, and what to publish.

## Requirements

| | |
|---|---|
| PHP | `^8.5` |
| Laravel | `^13.0` |
| Extensions | `ext-intl`, `ext-calendar` |

Both extensions are required rather than suggested. `ext-intl` supplies locale-aware formatting and
the Windows timezone mapping; `ext-calendar` supplies Julian Day conversion and `easter_days()`,
which the calendar and holiday modules will build on. Making them optional would mean two code
paths through every formatting call for the sake of a configuration almost nobody runs.

The PHP floor is `^8.5` because the value objects use `clone ($this, [...])` and `#[\NoDiscard]`
throughout. Neither has a polyfill.

## Install

```bash
composer require laranail/chrono
```

The service provider is auto-discovered. Two facades are registered: `Chrono` and `Timezones`.

## Publish the configuration

```bash
php artisan vendor:publish --tag=laranail::chrono-config
```

That writes `config/laranail/chrono.php`. Application code reads it as
`config('laranail.chrono.*')` — vendor-namespaced, matching the `laranail::chrono.<command>` shape
that commands use.

Publishing is optional — every key has a working default, and the package boots with none of them
set.

## Publish the translations

```bash
php artisan vendor:publish --tag=laranail::chrono-translations
```

That writes to `lang/vendor/laranail-chrono/{locale}/`, which is where validation messages are
overridden.

## Check the installation

```php
use Simtabi\Laranail\Chrono\Facades\Timezones;

Timezones::version();            // '2026.3' — the tzdata release PHP is carrying
Timezones::of('Africa/Nairobi'); // a Timezone value object
```

If `version()` reports something two or more years old, the host is quietly wrong about any country
that has changed its rules since — see [Daylight saving](daylight-saving.md#keeping-the-database-current).

## Verify the extensions

```bash
php -r 'printf("php tzdata=%s  icu=%s  icu tzdata=%s\n",
    timezone_version_get(), INTL_ICU_VERSION, IntlTimeZone::getTZDataVersion());'
```

The two tzdata versions are shipped independently and frequently disagree — one machine reported
`2025.3` from PHP and `2019a` from ICU. That gap is expected and harmless here, because ICU is used
only for human-facing text and never for offsets, rules or resolution.

---

[← Docs index](../README.md#documentation)
