# Commands

Four Artisan commands. Each has a namespaced name and a short alias, so `laranail::chrono.doctor`
and `chrono:doctor` are the same command.

## `chrono:show`

Everything known about one zone — what you run when a timestamp looks wrong.

```bash
php artisan chrono:show Africa/Nairobi
php artisan chrono:show KE
php artisan chrono:show "Pacific Standard Time"
```

It accepts anything the resolver does and reports what the input actually resolved to, which is
usually the answer when a zone is behaving unexpectedly. Output covers the canonical identifier and
kind, the current offset and abbreviation, whether daylight saving is in effect and whether the zone
observes it at all, the size of its shift, the local time, and the previous and next transitions.

When the input resolves to nothing it exits non-zero and suggests candidates rather than just
failing.

## `chrono:list`

The catalogue **as this application has configured it** — not the full 419.

```bash
php artisan chrono:list --region=Africa
php artisan chrono:list --country=KE --country=TZ
php artisan chrono:list --search=nairobi
php artisan chrono:list --group=offset
php artisan chrono:list --format=ids
```

| Option | |
|---|---|
| `--region` | A continent, e.g. `Africa` |
| `--country` | ISO 3166-1 alpha-2, repeatable |
| `--search` | Matches identifier, city or country |
| `--group` | `none`, `continent`, `country`, `offset` |
| `--format` | `table`, `json`, `csv`, `ids` |

Worth running when a picker and a validation rule disagree: the configured catalogue and the full
list are rarely the same, and the difference is invisible until a user hits it.

## `chrono:doctor`

"Is this host's date data trustworthy?"

```bash
php artisan chrono:doctor
php artisan chrono:doctor --strict   # warnings become failures
```

It reports the checks nobody thinks to make:

- **PHP's tzdata release**, warned about when older than `doctor.min_tzdata`. Zones change several
  times a year by government decree, so a host two years stale is quietly wrong about at least one
  country.
- **ICU's tzdata**, warned about when it disagrees with PHP's. The two ship independently — one
  machine was found running PHP `2025.3` against ICU `2019a`, a six-year gap in the data behind
  every localised zone name. Offsets come from PHP and names from ICU, so the two can describe the
  same zone differently.
- **The configured `default` and `fallback`**, failing if either resolves to nothing.
- **The catalogue**, reporting how many zones this application offers against how many it knows, and
  failing outright when the configured restrictions match nothing at all — an empty picker and a rule
  that rejects everything is a broken application, not a stale one.
- **The daylight-saving pair in force**, always. The defaults reproduce PHP's silent resolution, and
  an application that never chose is worth telling.
- **The process default against `app.timezone`**, warned about when they differ. Eloquent assumes
  they match; moving one without the other silently shifts every timestamp it writes.

`--strict` turns warnings into a non-zero exit for one run; `doctor.strict` does it permanently,
which is what a CI pipeline wants. A genuine failure exits non-zero either way.

## `chrono:sync`

Regenerate the generated enums and alias map from this host's tz database.

```bash
php artisan chrono:sync
php artisan chrono:sync --check   # report drift, write nothing, exit non-zero
```

`--check` is what CI runs. See [Generated data](generated-data.md) for what is generated and why the
alias map has to be curated.

The generators are plain scripts, so they also run without a booted framework:

```bash
php tools/generate-enums.php --check
composer sync-check
```

---

[← Docs index](../../README.md#documentation)
