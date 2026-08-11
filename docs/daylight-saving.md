# Daylight saving

The two wall-clock readings a year that name no instant, or two — and the zones that break every
assumption you might make about them.

## The problem

A wall-clock reading carries no offset, so around a transition it may map to zero instants or to
two. PHP resolves both silently.

```php
// This local time never happened: the clock jumped from 02:00 to 03:00.
new DateTimeImmutable('2026-03-08 02:30', new DateTimeZone('America/New_York'));
// => 2026-03-08 03:30 EDT, with no warning

// This one happened twice: the clock fell back from 02:00 to 01:00.
new DateTimeImmutable('2026-11-01 01:30', new DateTimeZone('America/New_York'));
// => one of the two, with no way to know which
```

Worse, **PHP's choice for the ambiguous case is not consistent between zones.** Verified on 8.5.3:

| Reading | PHP returns |
|---|---|
| `2025-10-26 01:30` `Europe/London` | the **later** instant (GMT) |
| `2025-11-02 01:30` `America/New_York` | the **earlier** instant (EDT) |

A booking system storing user-entered times in two cities gets opposite disambiguation from the same
build, and nothing surfaces it.

## Making the choice explicit

```php
use Simtabi\Laranail\Chrono\Core\Enums\AmbiguityPolicy;
use Simtabi\Laranail\Chrono\Core\Enums\GapPolicy;

$newYork = Timezones::of('America/New_York');

$newYork->at('2026-03-08 02:30');                            // 03:30 EDT — GapPolicy::Forward
$newYork->at('2026-03-08 02:30', GapPolicy::Backward);       // 01:30 EST
$newYork->at('2026-03-08 02:30', GapPolicy::Throw);          // SkippedLocalTime

$newYork->at('2026-11-01 01:30', ambiguity: AmbiguityPolicy::Earlier); // 01:30 EDT
$newYork->at('2026-11-01 01:30', ambiguity: AmbiguityPolicy::Later);   // 01:30 EST
$newYork->at('2026-11-01 01:30', ambiguity: AmbiguityPolicy::Throw);   // AmbiguousLocalTime
```

`Forward` and `Earlier` are the defaults because they reproduce PHP's own behaviour — adopting this
package changes nothing until you opt in. `Throw` is the right setting anywhere a human will be
billed for the result.

## Branching instead of catching

Exceptions are wrong for a booking form: the user has not made a mistake, they have hit a real
ambiguity in their own calendar. Inspect first.

```php
$status = $newYork->inspect($request->input('starts_at'));

$instant = match (true) {
    $status->isValid()     => $status->earlier(),
    $status->isGap()       => $status->resolve(GapPolicy::Forward),
    $status->isAmbiguous() => $this->askWhichOne($status->candidates),
};
```

## Which zone observes what

```php
$newYork->observesDst();          // true — in the current era
$newYork->observesDstIn(2026);    // true
$newYork->isDst($instant);        // at a given moment
$newYork->dstSavings();           // how far the clock moves
$newYork->nextTransition();       // ?Transition
$newYork->transitionsIn(2026);    // TransitionCollection
```

`observesDst()` asks about the current era rather than all of history, because that is the question
applications have. Egypt dropped daylight saving in 2015 and reinstated it in 2023; "has it ever?"
is almost never what you want to know.

## Assumptions that are wrong

Each of these is a real zone, asserted in the test suite.

| Assumption | Counterexample |
|---|---|
| A shift is one hour | `Australia/Lord_Howe` shifts **30 minutes**; `Antarctica/Troll` shifts **two hours** |
| A gap is at most a few hours | `Pacific/Apia` skipped **a whole calendar day** on 2011-12-30 |
| Offsets are whole hours | `Asia/Kathmandu` is `+05:45`; `Pacific/Chatham` is `+12:45`/`+13:45` |
| Offsets fall within ±14 hours | `Asia/Manila` LMT is **−15:56:08**; `America/Metlakatla` is **+15:13:42** |
| A zone changes at most twice a year | `Africa/Cairo` changed **four times** in 2014, shifting for Ramadan on top of daylight saving |
| `format('I')` tells you if DST is active | `Europe/Dublin` runs **negative** daylight saving and reports `'1'` in January, `'0'` in July |
| `Etc/GMT+5` is UTC+05:00 | It is **UTC−05:00**. The sign is inverted, by a POSIX convention IANA inherited |
| A zone always has transitions | `getTransitions()` returns **`false`**, not `[]`, for `+03:00` and `CEST` |

The last one matters more than it looks: resolving a user-supplied `+03:00` deliberately produces
such a zone, so any code reading transitions directly is one call away from a `count(false)` crash.
Everything here goes through a scanner that normalises it.

## Elapsed time is not wall-clock difference

```php
$start = new DateTimeImmutable('2026-03-08 00:00', $newYork);
$end   = new DateTimeImmutable('2026-03-09 00:00', $newYork);

$start->diff($end)->d;                              // 1  — "one day"
$end->getTimestamp() - $start->getTimestamp();      // 82800 — 23 hours
```

Both are true; they answer different questions. Anything billing by duration wants the second, which
is what [`Chrono::humanize()`](tools/humanize.md) uses.

Related: `DateInterval` distinguishes the two where `modify()` does not. From `2026-03-07 23:00` in
New York, `add(new DateInterval('P1D'))` gives `03-08 23:00 EDT` while `add(new DateInterval('PT24H'))`
gives `03-09 00:00 EDT` — but `modify('+1 day')` and `modify('+24 hours')` are identical. Most
write-ups state this backwards.

## Keeping the database current

Zones change several times a year by government decree. A host running a two-year-old tzdata is
quietly wrong about any country that has legislated since.

```php
Timezones::version();  // '2025.3'
```

The package's own generated data is guarded against drift — see
[Generated data](tools/generated-data.md).

---

[← Docs index](../README.md#documentation)
