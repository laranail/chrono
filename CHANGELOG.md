# Changelog

All notable changes to `laranail/chrono` are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.6] - 2026-08-11

### Changed

- **CI runs the suite and static analysis inside the development container**, so a contributor's
  `make docker-test` and the canonical CI job agree on the PHP build, the extensions, the tz release
  and the ICU version. ICU is the one that prompted this: every localised zone name and human date
  format comes from it and its output moves between major versions, so passing on ICU 78 locally
  while CI ran ICU 74 meant two suites wearing one name.
- `sync-check` in CI no longer needs an extension pin threaded through the workflow — the image
  carries the pinned release by construction, so the gate cannot skip while still reporting green.
- A second `host` job keeps running on the runner's own PHP as a compatibility check. This package
  exists because hosts disagree about time; proving it on exactly one host would be an odd way to
  make that argument, and a disagreement between the two legs is information worth having.

## [0.1.5] - 2026-08-11

### Fixed

- **An unreachable cache took `Timezones` down with it.** `CachedTimezoneRepository` let a store
  failure propagate, so a `cache` table nobody had migrated, a Redis that was down, or a driver
  misconfigured in one environment turned `Timezones::of()` into a `QueryException`. A cache is an
  optimisation, and one that can fail a request is not one; reads, writes and flushes now fall
  through to the tz database, which is always correct and only slower. Found by running the suite in
  the development container, where the default store had no table behind it.

### Added

- A `Makefile` as the single entry point — `make` lists host targets and container targets
  separately, with the pinned tz release named in the heading.
- A development container — `docker/Dockerfile` and `compose.yaml` — carrying the tz release this
  repository was generated against, so `composer sync-check` and the generated-data tests are real
  without installing anything on the host:

  ```bash
  docker compose run --rm chrono composer test
  ```

  It reads the pinned release from `resources/tzdata-version.txt` at build time rather than
  restating it, and **fails to build** if the extension does not then report that release. An image
  quietly carrying the wrong database would make every check inside it skip, which looks identical to
  passing.

  It is a development environment, not an artifact: nothing is published to a registry, and using
  `laranail/chrono` never requires it. A `docker.yml` workflow builds it when the image or the pin
  moves, so a tool nobody runs daily cannot rot unnoticed.

  Built on Alpine — 253MB against the 906MB a Debian image cost — with `icu-data-full`, without
  which `intl` loads, every call succeeds, and every locale silently renders in English.

## [0.1.4] - 2026-08-11

### Added

- A weekly `tzdata-bump.yml` and `tools/tzdata-release.php`. The workflow regenerates against the
  newest PECL release, runs the suite, `sync-check` and `lint` against it, and opens a pull request
  saying whether any identifier, abbreviation or alias actually changed or whether the release only
  altered transition rules. The proof happens in the bump job because a pull request raised by a
  workflow does not trigger CI itself, and an unverified proposal would be worse than none. Where an
  organisation forbids workflow tokens from opening pull requests, it pushes the verified branch and
  files an issue with the link rather than throwing the work away.

- The tz database moved from **2026.1 to 2026.3**. Nothing in the generated catalogue changed —
  419 canonical identifiers, 179 legacy, 144 abbreviations and 128 curated aliases either side, with
  only the version stamps differing. That release altered transition rules, not the set of zones.

### Changed

- When `composer sync-check` skips, it now prints the two commands that would make it run here —
  `pecl install timezonedb-<pinned>` and the `.ini` line — with the pinned release substituted in. A
  skip that explains itself and stops is a message people learn to scroll past.
- The tz database pin is single-sourced. The workflows read `resources/tzdata-version.txt` at run
  time instead of restating the release, so regenerating *is* pinning and the two cannot drift.
  Previously the version appeared in three workflow files, which made a bump a change to
  `.github/workflows/*` — something the default `GITHUB_TOKEN` may not push, so the automation could
  do everything except the last step. Two things that must agree are worse than one thing.

## [0.1.3] - 2026-08-11

### Changed

- **`composer sync-check` is a real check in CI again.** The release the generated files were built
  against is now committed in `resources/tzdata-version.txt`, written by the generators themselves,
  and the workflows pin the PECL `timezonedb` to it. Previously the gate skipped on every CI run,
  because Ubuntu's PHP reports `0.system` and there was nothing to compare against.
- It now distinguishes two failures that look alike: *the data is stale* (the generators produce
  something else on the right database) and *the host cannot tell* (the database is not the one the
  files describe). The second is fatal in CI, where it means the pin has broken and the gate has
  silently stopped running, and merely reported on a laptop whose PHP bundles a different release.
- The weekly drift job asserts the pin loaded, rather than only that some version exists. A detector
  that cannot measure should stop rather than report.

## [0.1.2] - 2026-08-11

### Fixed

- **A system-tzdata build put files in the timezone catalogue.** Built `--with-system-tzdata` — which
  is how the official Docker images, Debian and Ubuntu all ship PHP — `listIdentifiers()` reports
  whatever is in `/usr/share/zoneinfo`, including `tzdata.zi` and `leapseconds`. They reached a
  picker as if they were places, and `new DateTimeZone('leapseconds')` throws, so a catalogue built
  from the raw list was one `->of()` away from a fatal error on the most common production image
  there is. Entries that name a file are filtered out.
- **Every zone read as placeless on those same builds.** `Location` detected an absent location by
  the bundled database's `??`/-90/-180 sentinel; a system build writes `0.0/0.0` with a `?` comment
  instead, so rule-less zones came back as a location in the Gulf of Guinea. A zone with no country
  is not a place, which is the only test that holds across builds.
- `chrono:doctor` warned that tzdata was stale on every well-maintained Debian host, because
  `timezone_version_get()` returns the literal `0.system` there and no comparison against it can
  succeed. It now reports where the data comes from and says the OS package is what keeps it current.
- `tools/generate-alias-map.php` died rather than stepping over a non-zone entry.

### Added

- `TimezoneRepository::usesSystemDatabase()`, surfaced as `Timezones::usesSystemDatabase()`.

### Changed

- `composer sync-check` and the generated-data tests skip on a host whose tz database carries no
  release, instead of failing. Comparing files generated elsewhere byte for byte is not a check when
  there is no version to compare against — it is a red build no commit caused. Drift is still caught
  weekly by `tzdata.yml`.
- The generated-enum parity test compares against the list the package exposes rather than PHP's raw
  one, so it asserts what consumers actually get.
- GitHub Actions re-pinned: `actions/checkout` v7.0.1, `softprops/action-gh-release` v3.0.2 and
  `actions/github-script` v9.0.0, each to the commit its tag points at.

## [0.1.1] - 2026-08-11

### Added

- **Traits.** Eight of them, so a class can have the package's behaviour in one `use` line and keep
  it outside a framework. `InteractsWithClock`, `InteractsWithTimezones`, `ResolvesLocalTimes`,
  `ConvertsTimezones`, `PresentsTimezones` and `RendersDateTimes` in `Core\Concerns`, composed by
  `InteractsWithChrono`; `HasTimezone` for an Eloquent model; `FreezesChronoTime` for a test case.
  Inside an application they return the *configured* services, so a helper using one cannot quietly
  opt out of the daylight-saving policy and catalogue everything else obeys; outside one they
  construct working defaults with no wiring.
- `Core\Support\ServiceResolver`, the seam that makes both of those true at once — a closure the
  provider installs at boot, and nothing in `Core` that knows what a container is.
- `TimeConverter::to()` now accepts anything the resolver does, matching `Timezones::of()`.
- `select.shape` on the presenter and the Blade component: one setting carries a picker's grouping
  and label template together, and `<x-chrono::timezone-select shape="flat" />` overrides it for one
  field. `TimezonePresenter::shape()` and `toShape()` expose the same four shapes to code.
- `DstPolicy`, `DisplayOptions` and `SelectOptions` — the configured daylight-saving pair, offset
  shape and picker defaults as value objects, bound in the container and reachable through
  `Chrono::display()`.
- `Timezones::onGap()`, `onAmbiguity()`, `withDst()` and `preservingAliases()`, and
  `Timezone::withDst()`, for narrowing those choices to one service or one zone.
- Every entry point now accepts a string-backed enum case or any `Stringable` — `Timezones::of(
  Timezone::AmericaNewYork)` — by unwrapping it to the string it spells and judging that string like
  any other. An abbreviation enum still has to earn its answer from the abbreviation strategy.
- `chrono:doctor` reports the daylight-saving pair in force and the size of the configured catalogue,
  and fails outright when that catalogue matches no zones.
- `TimezoneRepository::isCanonical()`.

### Fixed

- **`DateFormatter` threw on the most ordinary input there is.** ICU rejects the zone names PHP
  produces for an ISO 8601 string — `Z` for a UTC suffix, `+03:00` for an offset — so formatting an
  instant parsed from a JSON payload, an API response or a round-tripped `format('c')` was an
  `IntlException` rather than a rendering. Those zones now render as a fixed `GMT±HH:MM`.
- **Nine configuration keys were documented and never read**: `dst.on_gap`, `dst.on_ambiguous`,
  `display.offset_format`, `display.datetime_format`, `select.shape`, `select.placeholder`,
  `doctor.strict`, `resolution.canonicalise` and `catalogue.sort`. Setting `dst.on_gap = throw` did
  nothing at all — the one setting the package exists for. All are wired, and `ConfigWiringTest` now
  walks the whole config file and fails on any key the source never mentions.
- A deprecated identifier reported no country, no flag and no coordinates, because PHP gives an alias
  the `??`/-90/-180 sentinel rather than a place. `Timezone::location()` follows the alias, so a
  picker offering legacy spellings no longer shows half its rows as placeless.
- `Chrono::convert()` resolved every target zone once per input rather than once per call, so a
  five-instant by ten-zone grid ran the resolver fifty times.
- The picker's embedded JSON is hex-escaped, so a translated country name or a custom label template
  cannot close the `<script>` element early.
- The `Chrono` facade's docblock omitted `present()` and `convert()`.
- The package's own `TestCase` bound `ClockInterface` but not `Clock`, and did not rebuild the
  singletons already made from the old clock. It now uses the shipped `FreezesChronoTime`, which
  does both — freezing by hand there meant the helper consumers are told to use was never exercised.

### Changed

- `Timezone::at()` takes nullable policies and falls back to the application's configured pair, so
  `dst.on_gap = throw` reaches call sites written before anybody thought about daylight saving. An
  explicit argument still wins for that call.
- `TimeConverter` defaults its date and offset formats to `display.*` rather than to its own
  literals, so a converted time and a picker label render the same way.
- `catalogue.sort` accepts a `-` prefix to reverse the order.

### Performance

- `Timezone` construction resolves its kind through a hash rather than scanning the 419-entry
  identifier list, turning a full collection build from ~175,000 string comparisons into 419 lookups.
- The abbreviation enum builds `listAbbreviations()` once per process instead of once per method
  call; the identifier enum does the same for the canonical list.
- Country names in a picker resolve once per locale and code rather than once per zone.

## [0.1.0] - 2026-08-11

Initial public release.

### Added

- **Timezone catalogue** — a `Timezone` value object, a fluent `TimezoneQuery`, and a repository
  over PHP's tz database with an optional PSR-16 caching decorator whose keys self-invalidate on a
  tzdata change.
- **Resolution** — a nine-strategy chain turning identifiers, deprecated aliases, UTC offsets,
  Windows ids, country codes, locales, abbreviations and city names into a canonical identifier.
  Ambiguous input is reported with alternatives rather than guessed.
- **Daylight saving** — transition scanning plus `GapPolicy` and `AmbiguityPolicy` for the two
  wall-clock readings a year that name no instant, or two. Defaults reproduce PHP's own behaviour.
- **Temporal value types** — `LocalDate`, `TimeOfDay`, `YearMonth`, `MonthDay` and `Duration`, for
  the questions `DateTimeImmutable` cannot answer without lying.
- **Conversion** — a fluent converter answering "what time is that, over there?" for one instant or
  many, in one zone or many.
- **Presentation** — a builder producing `<optgroup>`-ready arrays, API payloads, objects or JSON
  from one description, grouped by continent, country or offset.
- **Formatting** — machine formats that never localise, human formats resolved through ICU
  skeletons, and parsing that closes the `createFromFormat` timezone trap.
- **Humanised time** — relative phrasing built on `MessageFormatter`, so locales with more than two
  plural categories are correct. English, Swahili, Arabic and French built in.
- **Laravel integration** — service provider, `Chrono` and `Timezones` facades, published config,
  helpers, an Eloquent cast, seven validation rules, four Artisan commands, translations, and a
  progressively-enhanced timezone picker with vanilla-JS search.
- **Generated data** — the IANA, legacy and abbreviation enums, the `Tz` constants class, the
  `laranail/enumerator` bridge and the curated alias map, all regenerated from the live database and
  guarded by a byte-for-byte sync check in CI.

### Notes

- Case names in the generated `Timezone` enum match `laranail/package-tools`, so migrating is a
  one-line `use` change. See [UPGRADING.md](UPGRADING.md).
- Calendars, recurrence, intervals, reporting periods, business days and astronomy are planned for
  `v0.2` and `v0.3`.