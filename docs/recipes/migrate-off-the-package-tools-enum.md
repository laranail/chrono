# Migrate off the `package-tools` timezone enum

A one-line change per file.

`laranail/package-tools` shipped a generated `Timezone` enum. This package is its new home, with
identical case names and identical values.

```diff
-use Simtabi\Laranail\Package\Tools\Enums\Timezone;
+use Simtabi\Laranail\Chrono\Core\Enums\Timezone;
```

Everything that worked continues to:

```php
Timezone::AfricaNairobi->value;        // 'Africa/Nairobi'
Timezone::AmericaNewYork->value;       // 'America/New_York'
Timezone::Utc->value;                  // 'UTC'
Timezone::AsiaTokyo->toDateTimeZone(); // DateTimeZone
```

## What you gain

The enum now carries a little more, from a hand-written concern rather than generated code:

```php
Timezone::AfricaNairobi->city();       // 'Nairobi'
Timezone::AfricaNairobi->kind();       // TimezoneKind::Canonical
Timezone::AfricaNairobi->canonical();  // follows an alias
Timezone::AfricaNairobi->toTimezone(); // the full value object
```

And two enums that did not exist before: `TimezoneLegacy` for the 179 backward-compatible
identifiers, and `TimezoneAbbreviation` for the 144 abbreviations.

## Why it moved

A timezone enum has nothing to do with building Laravel packages. More practically, it now lives
beside the alias map, the resolver and the parity tests that keep it honest — the old copy had a
parity test but no way to answer what `Asia/Calcutta` should become.

## The old copy

It remains in `package-tools` for one release cycle, marked `@deprecated`, and is then removed.
There is deliberately no `class_alias` and no dependency from `package-tools` back to this package:
this package depends on `package-tools` for its service provider, so an edge in the other direction
would be a cycle.

Because both files are generated from the same database by the same algorithm, they cannot
meaningfully diverge while both exist.

---

[← Docs index](../../README.md#documentation)
