# Changelog

All notable changes to `laranail/chrono` are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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