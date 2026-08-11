# Release

How a version of this package is cut.

## Versioning

Semantic versioning. While pre-1.0 the package follows the laranail convention of a single moving
`v0.1.0` tag: the tag is re-pointed as work lands, consumers constrain on `^0.1`, and history stays
a single `Initial release` commit. New SemVer versions begin at 1.0.

## The public surface

Supported: the `Chrono` and `Timezones` facades, everything under `Core\Contracts`, the published
`config/chrono.php` keys, the value objects in `Core\Timezone\Value`, the enums in `Core\Enums`, and
the helpers.

Internal, and free to change: everything under `Core\*\Support` and `Core\*\Resolver`.

## Before tagging

```bash
composer lint         # Pint, PHPStan (both configs), deptrac guard, Rector
composer test         # Pest, excluding the tzdata group
composer sync-check   # generated data matches the runner's tz database
composer audit
```

`sync-check` is the one that catches the failure specific to this package: the IANA database changes
several times a year, so a release cut against stale generated data would ship an enum that
disagrees with the host it runs on.

Then update `CHANGELOG.md` under the version being cut. The release workflow extracts that section
as the GitHub release body, so a version with no changelog entry publishes an empty description.

## The workflow

Pushing a `v*.*.*` tag runs `.github/workflows/release.yml`, which installs runtime dependencies,
generates a CycloneDX SBOM, extracts the changelog section, and publishes the release.

## Keeping generated data current

A weekly `tzdata.yml` workflow runs the `tzdata` test group — the assertions that track government
decrees rather than statute — and opens an issue when they drift. It is deliberately separate from
the main suite so a CI image rebuilt mid-sprint cannot turn anyone's pull request red.

When it fires:

```bash
php tools/generate-alias-map.php
php tools/generate-enums.php
composer test-tzdata
```

Review the diff before committing. A changed alias mapping or a removed identifier is a real
upstream change and deserves a changelog entry.

---

[← Docs index](../README.md#documentation)
