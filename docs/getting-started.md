# Getting started

The mental model, and the first handful of calls.

## The model

Three ideas, in order of how often they matter.

**Store instants, display zones.** The package never calls `date_default_timezone_set()` and never
rewrites `app.timezone`. Laravel's storage contract assumes the process default matches that value,
so moving it corrupts every stored timestamp. A timezone here is something you resolve on the way in
and apply on the way out.

**A wall-clock reading is not an instant.** `2026-03-08 02:30` in New York names no moment at all,
and `2026-11-01 01:30` names two. Converting one to the other is a decision, and the package makes
you able to see it.

**"Now" is injected.** Every current-time read goes through a PSR-20 clock, so a daylight-saving
assertion means the same thing in five years as it does today.

## Resolving a zone

```php
use Simtabi\Laranail\Chrono\Facades\Timezones;

Timezones::of('Africa/Nairobi');         // exact identifier
Timezones::of('Asia/Calcutta');          // Asia/Kolkata — a deprecated alias, canonicalised
Timezones::of('US/Eastern');             // America/New_York
Timezones::of('Pacific Standard Time');  // America/Los_Angeles — a Windows id
Timezones::of('+03:00');                 // a fixed-offset zone, honestly
Timezones::of('KE');                     // Africa/Nairobi
Timezones::of('en_KE');                  // Africa/Nairobi
Timezones::of('nairobi');                // Africa/Nairobi
```

`of()` throws when nothing matches; `tryOf()` returns null. Neither ever guesses — see
[Resolution](tools/resolution.md).

## Querying the catalogue

```php
use Simtabi\Laranail\Chrono\Core\Enums\Region;

Timezones::query()
    ->inRegion(Region::Africa)
    ->observingDst(false)
    ->orderByOffset()
    ->toSelectOptions();
```

Deprecated aliases and `Etc/*` zones are excluded by default, so a picker cannot offer the same zone
twice under two names. Full surface in [Querying](tools/query.md).

## Asking a zone about itself

```php
$nairobi = Timezones::of('Africa/Nairobi');

$nairobi->offset()->format();   // '+03:00'
$nairobi->abbreviation();       // 'EAT'
$nairobi->countryCode();        // 'KE'
$nairobi->observesDst();        // false
```

## Reading a wall-clock time

```php
use Simtabi\Laranail\Chrono\Core\Enums\GapPolicy;

$newYork = Timezones::of('America/New_York');

$newYork->at('2026-06-15 09:00');                    // unambiguous
$newYork->at('2026-03-08 02:30');                    // 03:30 EDT, as PHP would
$newYork->at('2026-03-08 02:30', GapPolicy::Throw);  // SkippedLocalTime

$status = $newYork->inspect('2026-11-01 01:30');
$status->isAmbiguous();  // true
$status->candidates;     // both instants, so the user can be asked which they meant
```

The defaults reproduce PHP's own behaviour, so adopting this package changes nothing until you
choose otherwise. [Daylight saving](daylight-saving.md) explains when to choose otherwise.

## Formatting and phrasing

```php
use Simtabi\Laranail\Chrono\Core\Enums\NamedFormat;
use Simtabi\Laranail\Chrono\Facades\Chrono;

Chrono::format()->format($when, NamedFormat::MediumDate, locale: 'de_DE'); // '15. Juni 2026'
Chrono::format()->format($when, NamedFormat::Iso8601);                      // never localised
Chrono::humanize()->diffForHumans($comment->created_at, locale: 'ar');      // 'منذ ٣ أيام'
```

See [Formatting](tools/format.md) and [Humanised time](tools/humanize.md).

## Where next

- [Configuration](configuration.md) — every key and its environment variable
- [Daylight saving](daylight-saving.md) — gaps, ambiguity, and the zones that break assumptions
- [The catalogue](catalogue.md) — canonical, deprecated, fixed and legacy identifiers
- [Architecture](architecture.md) — why the core is framework-free, and what is coming next

---

[← Docs index](../README.md#documentation)
