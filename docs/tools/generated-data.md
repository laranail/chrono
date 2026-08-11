# Generated data

Five artefacts derived from the live tz database, and the check that stops them going stale.

## What is generated

| File | Contents |
|---|---|
| `Core/Enums/Timezone.php` | 419 canonical identifiers |
| `Core/Enums/TimezoneLegacy.php` | 179 backward-compatible identifiers |
| `Core/Enums/TimezoneAbbreviation.php` | 144 abbreviations |
| `Core/Timezone/Support/AliasMap.php` | 128 alias → canonical pairs |
| The country index | Built at runtime from `getLocation()`, not committed |

All are excluded from Pint and Rector. A reformatter touching them would put the committed file and
the generator permanently at odds.

## Regenerating

```bash
php tools/generate-alias-map.php
php tools/generate-enums.php
```

## The check

```bash
composer sync-check
```

Re-runs every generator in check mode and compares byte for byte. It fails when the committed files
disagree with the machine's tz database, and reports all three version numbers so the cause is
obvious:

```
Generated data is stale.

  enums:
    Timezone.php is out of sync with the runner's tz database.

The runner reports tzdata 2025.3 (ICU 57.2, ICU tzdata 2019a).
```

This runs in the static-analysis workflow, so a pull request cut against stale data cannot merge.

## Why the alias map is curated

It cannot be derived, and it cannot be read from either library. `new DateTimeZone('Asia/Calcutta')
->getName()` returns the alias unchanged, and `IntlTimeZone::getCanonicalID('Asia/Calcutta')`
returns `'Asia/Calcutta'` as well. `getIanaID()` fatals on older ICU builds.

So the generator works in three passes:

1. Fingerprint every canonical zone by its 1970–2038 transition history. An alias is an exact link,
   so its target must share that fingerprint.
2. Where several zones share one — and they often do — narrow by the country code both carry, then
   by identical coordinates.
3. Where that still leaves more than one, take the curated answer, which follows the IANA `backward`
   file.

Every emitted pair is then **re-validated** against the rule that defines an alias: identical rules.
A curated entry that is merely plausible fails the build rather than shipping.

Two subtleties the generator encodes:

- Validation compares rules *without* the abbreviation. `GMT` and `UTC` are the same instant forever
  and IANA links one to the other, yet they report different abbreviations; a stricter comparison
  would reject a pair that is correct by definition.
- `Etc/*`, `CET`, `EST`, `MET`, `MST7MDT` and friends are excluded. They have no canonical target —
  they carry their own rules or are fixed offsets. So are `GMT`, `GMT+0`, `GMT-0` and `UCT`, which
  are abbreviation and offset zones rather than region zones, and return `false` from both
  `getTransitions()` and `getLocation()`.

## Case-name collisions

The generator refuses to write an enum where two identifiers normalise to one case name, since that
would be a fatal redeclaration. Across all 598 identifiers there are currently none.

## When the weekly job fires

A `tzdata.yml` workflow runs the decree-driven assertions weekly and opens an issue on drift.
Regenerate, review the diff, and record anything user-visible in the changelog — a changed alias
mapping or a removed identifier is a real upstream change.

---

[← Docs index](../../README.md#documentation)
