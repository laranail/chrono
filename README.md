# laranail/chrono

[![Latest version on Packagist](https://img.shields.io/packagist/v/laranail/chrono.svg)](https://packagist.org/packages/laranail/chrono)
[![Tests](https://github.com/laranail/chrono/actions/workflows/tests.yml/badge.svg)](https://github.com/laranail/chrono/actions/workflows/tests.yml)
[![Static analysis](https://github.com/laranail/chrono/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/laranail/chrono/actions/workflows/static-analysis.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

> Timezones, formatting and humanised time for Laravel — a tzdata-parity IANA catalogue with a fluent query builder, a resolver that turns messy input into a canonical identifier, and daylight-saving conversion with explicit policies for the two wall-clock readings a year that name no instant, or two.

Built on a framework-free core that never calls `date_default_timezone_set()` and never rewrites `app.timezone`, so your storage stays UTC and zones remain an input and presentation concern. Every "now" comes from an injected PSR-20 clock, which is what makes daylight-saving behaviour testable. Requires PHP `^8.5` with `ext-intl` and `ext-calendar`, on Laravel `^13`.

## Install

```bash
composer require laranail/chrono
```

## Quick start

```php
use Simtabi\Laranail\Chrono\Facades\Timezones;

Timezones::of('Asia/Calcutta');          // Asia/Kolkata — the deprecated alias is canonicalised
Timezones::of('Pacific Standard Time');  // America/Los_Angeles — a Windows id from an Outlook invite
Timezones::of('KE');                     // Africa/Nairobi

Timezones::query()
    ->inRegion(Region::Africa)
    ->observingDst(false)
    ->orderByOffset()
    ->toSelectOptions();                 // grouped, offset-labelled, ready for a <select>

$newYork = Timezones::of('America/New_York');

$newYork->at('2026-03-08 02:30');                        // 03:30 EDT — that reading never existed
$newYork->at('2026-03-08 02:30', GapPolicy::Throw);      // SkippedLocalTime
$newYork->inspect('2026-11-01 01:30')->isAmbiguous();    // true — it happened twice
```

## Why

PHP resolves both pathological wall-clock readings silently, and its choice for the ambiguous one is *not consistent between zones*: `Europe/London 2025-10-26 01:30` yields the later instant while `America/New_York 2025-11-02 01:30` yields the earlier, from the same build. A booking stored in two cities gets opposite disambiguation and nothing says so. This package makes that choice explicit, and keeps it the same everywhere.

## <a name="documentation"></a>Documentation

Hosted at **[opensource.simtabi.com/documentation/laranail/chrono](https://opensource.simtabi.com/documentation/laranail/chrono/)**.

### Guides
- [Installation](docs/installation.md) — requirements, install, what to publish
- [Getting started](docs/getting-started.md) — the mental model and the first calls
- [Configuration](docs/configuration.md) — every key and its environment variable
- [Daylight saving](docs/daylight-saving.md) — gaps, ambiguity, and the zones that break assumptions
- [The catalogue](docs/catalogue.md) — canonical, deprecated, fixed and legacy identifiers
- [Architecture](docs/architecture.md) — the layering, the clock, and what ships when
- [Release](docs/release.md) — cutting a version, keeping generated data current

### Reference
- [Facades and helpers](docs/tools/facade.md) — `Chrono`, `Timezones`, and injecting instead
- [Traits](docs/tools/concerns.md) — one `use` line, with a container or without one
- [Resolution](docs/tools/resolution.md) — the nine-strategy chain, and when it refuses
- [Querying](docs/tools/query.md) — the fluent builder, collections and select shapes
- [Converting between zones](docs/tools/conversion.md) — one instant or many, one zone or many
- [Presentation](docs/tools/presentation.md) — optgroups, labels, field sets and output shapes
- [Temporal value types](docs/tools/temporal.md) — dates, times, durations PHP lacks
- [The `Timezone` object](docs/tools/timezone.md) — offsets, transitions, wall-clock readings
- [Formatting and parsing](docs/tools/format.md) — named formats, ICU skeletons, the parsing traps
- [Humanised time](docs/tools/humanize.md) — relative phrasing with correct plural rules
- [The picker component](docs/tools/blade.md) — progressive enhancement, search, a11y
- [Commands](docs/tools/commands.md) — show, list, doctor, sync
- [Casts and validation](docs/tools/validation.md) — storing a zone, and refusing bad input
- [Enums](docs/tools/enums.md) — three generated, nine authored
- [Generated data](docs/tools/generated-data.md) — the alias map and the sync check

### Recipes
- [Build a timezone picker](docs/recipes/build-a-timezone-picker.md)
- [Store a user's timezone](docs/recipes/store-a-user-timezone.md)
- [Resolve messy input](docs/recipes/resolve-messy-input.md)
- [Handle a DST gap or ambiguity](docs/recipes/handle-dst-gaps-and-ambiguity.md)
- [Test with a frozen clock](docs/recipes/test-with-a-frozen-clock.md)
- [Migrate from `pheg`'s time toolbox](docs/recipes/migrate-from-pheg-time.md)
- [Migrate off the `package-tools` enum](docs/recipes/migrate-off-the-package-tools-enum.md)

## Stability

Pre-1.0, with immutable tags — every release is its own `v0.1.x` and none is ever re-pointed, so a lockfile means something. Constraints resolve `^0.1`. Calendars, recurrence, intervals and business days are planned for `v0.2` and `v0.3`; see [the roadmap](https://opensource.simtabi.com/documentation/laranail/chrono/architecture).

## Contributing & security

Issues and PRs are welcome — see [CONTRIBUTING.md](CONTRIBUTING.md); `make` lists every task. Report vulnerabilities per
[SECURITY.md](SECURITY.md) (opensource@simtabi.com); participation follows the [Code of Conduct](CODE_OF_CONDUCT.md).

## License

MIT © Simtabi LLC. See [LICENSE](LICENSE).
