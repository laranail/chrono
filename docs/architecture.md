# Architecture

How the package is put together, and the reasoning behind the parts that look unusual.

## The layers

```
src/Core            the domain. No Illuminate, no Carbon, no other Simtabi\Laranail package.
src/Core/Concerns   consumer-facing traits. May reach every module; nothing may reach them.
src/                the Laravel shell: provider, facades, helpers.
```

`Concerns` is the framework-free twin of the shell — the same convenience offered through a `use`
line rather than a container binding — which is why it sits outside the module DAG below.

The boundary is enforced in CI by deptrac, not by convention. The point is not purity for its own
sake: it means the whole domain is unit-testable without booting a container, and could be extracted
to a framework-free package later without a rewrite.

Unit tests deliberately do not extend the Laravel `TestCase`. Exercising `src/Core` with no
container is the cheapest continuous proof that it stayed framework-free.

### Why deptrac is pinned to `^4.7`

Version 3.0.0 cannot parse PHP 8.5's `clone ($this, [...])`. It reports
`Syntax error, unexpected ','`, then prints `Violations 0` **and exits 0** — so the boundary would
be silently unenforced while CI stayed green. Since deptrac does not fail on a parse error, it runs
through `tools/deptrac-guard.php`, which fails on one. That guard is independent of clone-with and
exists so the same class of gap cannot reappear with a future language feature.

## Modules

`Core` is a modular monolith. The dependency graph is a DAG, also deptrac-gated.

| Module | Ships in | Owns |
|---|---|---|
| `Timezone` | v0.1 | Identifiers, offsets, transitions, resolution, querying, the gap and ambiguity engine |
| `Format` | v0.1 | Named formats, ICU skeletons, parsing |
| `Humanize` | v0.1 | Relative phrasing and durations |
| `Calendar` | v0.2 | 16 ICU calendar systems |
| `Interval` | v0.2 | Ranges and interval algebra |
| `Recurrence` | v0.2 | RFC 5545 rules, cron with a timezone |
| `Reporting` | v0.2 | Named periods, fiscal quarters |
| `Business` | v0.3 | Holidays, working hours, business days |
| `Astronomy` | v0.3 | Sunrise, sunset, twilight |

`Core\Concerns` sits outside that graph as its own layer. It may reach every module and nothing may
reach it — the framework-free twin of the shell, offering the same convenience through a `use` line
that the container offers through a binding. See [Traits](tools/concerns.md).

### How a framework-free trait finds a configured service

The traits have to be correct in two worlds: inside an application they must return the services
carrying *that* application's daylight-saving policy, catalogue and display settings, and outside one
they must still work with no wiring at all. A trait that built its own `new Timezones` would satisfy
the second and quietly break the first.

`Core\Support\ServiceResolver` is the seam. It holds one closure, installed by the service provider
at boot; without it every lookup returns null and the caller constructs a default. `Core` never
learns what a container is, so the deptrac boundary is untouched.

It is the package's only mutable global, which is a real cost and is why it is confined to lookup: it
holds no services, caches nothing, and cannot change behaviour except by returning something
configured elsewhere. Injection is still preferred wherever a call site can manage it, and every
trait accepts an explicit service that overrides the lookup.

## Determinism

Every current-time read routes through a PSR-20 `ClockInterface`. Nothing in `src/` calls `time()`,
`date()` or constructs a bare `DateTimeImmutable` for "now" — an architecture test enforces it, with
a single exemption for `SystemClock`, the class whose job that is.

For a package about daylight saving this is not a nicety. Reading the system clock directly would
mean its own answers changed twice a year, in ways no test could pin down.

```php
use Simtabi\Laranail\Chrono\Core\Testing\FrozenClock;

$winter = (new Timezones)->withClock(new FrozenClock('2026-01-15T12:00:00Z'));
$winter->of('America/New_York')->offset()->format();  // '-05:00', forever
```

The clock threads from `Timezones` into every `Timezone` and every query result, so a zone built
anywhere inherits it.

## Caching

Only transition-derived data is cached. Measured across all 419 zones: constructing every
`DateTimeZone` takes 1.17 ms, `getOffset()` 0.96 ms, `getLocation()` 0.70 ms and
`listAbbreviations()` 0.41 ms — every one cheaper than a round trip to a cache server. Reading full
transition histories takes 22.8 ms, and that is what the decorator covers.

Cache keys embed a fingerprint of PHP's tz database. Because the fingerprint is part of the key
rather than a version record checked alongside it, a tzdata upgrade moves the entire key space at
once: no purge step, no coordination between servers mid-deploy, and no window where one process
reads old rules while another reads new ones.

The fingerprint samples transitions from a **fixed** date range, not a window measured from `time()`.
A sliding window looks reasonable and is a slow leak: transitions drift in and out of it as days
pass, so the digest — and every key built from it — rotates continuously against an unchanged
database, silently orphaning every cached entry.

Keys use `[a-z0-9._-]` only. PSR-16 reserves `{}()/\@:`, and an identifier's `/` would be rejected
outright by a conforming driver.

## Why ICU is kept away from decisions

ICU ships its own copy of the timezone database, independently of PHP's, and the two drift badly —
one machine reported PHP `2025.3` against ICU `2019a`. ICU is therefore used only for human-facing
text: display names, locale-aware patterns, plural rules, the Windows id mapping. Offsets, rules,
resolution and canonicalisation all come from PHP's database.

ICU is also not an oracle for identity. `IntlTimeZone::getCanonicalID('Asia/Calcutta')` returns
`'Asia/Calcutta'`, and `getIanaID()` fatals on older builds — which is why the alias map is
generated and curated rather than queried.

## Generated data

Five artefacts are generated from the live database rather than hand-maintained: the canonical,
legacy and abbreviation enums, the alias map, and the country index. They carry no behaviour — the
enums `use` a hand-written concern — so the emitted text is a pure function of tzdata, which is what
makes a byte-for-byte sync check meaningful. See [Generated data](tools/generated-data.md).

## Deliberate divergences from the laranail defaults

| | Why |
|---|---|
| Rector pinned to `php85`, not `php83` | The `php83` set fights `clone with` and `#[\NoDiscard]`, which the value objects use throughout |
| Two PHPStan configs | `src/Core` runs level 10 with strict rules and no baseline; the Laravel shell runs level 8 with larastan |
| deptrac `^4.7`, not `^3.0` | See above — 3.0 silently fails to parse the codebase |
| `ext-intl` and `ext-calendar` required | Optional would mean two code paths through every formatting call |

---

[← Docs index](../README.md#documentation)
