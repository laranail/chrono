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

Re-runs every generator in check mode and compares byte for byte. It runs in the static-analysis
workflow, so a pull request cut against stale data cannot merge.

### It only means something on the right database

Byte-for-byte parity is a statement about one tz release. Run the same comparison on a host carrying
a different one and it reports a difference no commit caused — which is how a real gate becomes a red
build everybody learns to ignore.

So the release the files were generated against is committed, in `resources/tzdata-version.txt`,
written by the generators themselves rather than by hand. The check compares that against the
running database and distinguishes two failures that look alike and are not:

| | Meaning |
|---|---|
| **The data is stale** | The generators produce something else *on the right database*. Regenerate and commit. |
| **The host cannot tell** | The database is not the one the files describe. Nothing is being checked. |

The second is fatal in CI and merely reported elsewhere. In CI it means the pin has broken and the
gate has silently stopped running, which is worth failing over. On a contributor's laptop it means
their PHP bundles a different release — normal, and not theirs to fix:

```
sync-check skipped.

This host carries tzdata 2026.3; the generated files were built against 2026.1.
Byte-for-byte comparison across two releases reports a difference that no commit caused,
so it is not run here.
```

### How CI gets the right database

Ubuntu's PHP — and the official Docker images, and Debian's — is built `--with-system-tzdata`, so it
reads `/usr/share/zoneinfo` and `timezone_version_get()` returns the literal `0.system`. There is no
release to compare against, and for a while that meant this gate quietly skipped on every CI run.

The workflows install the PECL `timezonedb`, **pinned to the same release**:

```yaml
extensions: intl, calendar, timezonedb-2026.1
```

Moving to a newer database is therefore a deliberate, reviewable act: regenerate, which rewrites
`resources/tzdata-version.txt`, and move the pin to match. The two cannot drift apart without CI
saying so.

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

It runs against the pinned `timezonedb` too, and fails outright if that pin did not load. A drift
detector that cannot measure should stop rather than report: run it on a `0.system` host and it will
announce drift every week while detecting none of it.

---

[← Docs index](../../README.md#documentation)
