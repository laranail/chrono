# The `Timezone` value object

What you can ask a single zone, and what each answer means.

Immutable and cheap to build: constructing all 419 `DateTimeZone` objects takes 1.17 ms, so this
holds one rather than memoising derived state. The expensive call — reading transition histories —
goes through an injected scanner, which is where caching belongs.

```php
$nairobi = Timezones::of('Africa/Nairobi');
```

## Identity

```php
$nairobi->identifier;              // 'Africa/Nairobi'
$nairobi->city();                  // 'Nairobi' — the last path segment, underscores stripped
$nairobi->region();                // Region::Africa
$nairobi->countryCode();           // 'KE'
$nairobi->location();              // ?Location — latitude, longitude, comments
$nairobi->kind;                    // TimezoneKind::Canonical
$nairobi->canonicalIdentifier();   // follows an alias
$nairobi->equals('Africa/Nairobi');// compares canonically, so an alias matches its target
$nairobi->dst;                     // the daylight-saving pair this zone defaults to
```

### A deprecated zone still has a place

PHP gives an alias the `??`/-90/-180 sentinel rather than a location: `Asia/Kolkata` reports India
and real coordinates while `Asia/Calcutta` reports nothing, though they name the same city. This
package follows the alias, so a picker offering legacy spellings still shows their country, their
flag and their coordinates.

```php
Timezones::preservingAliases()->of('Asia/Calcutta')->countryCode();   // 'IN'
```

Zones that are rules rather than places — `EST`, `GMT`, `Etc/GMT+5`, `UTC` — still return `null`,
because no alias leads anywhere better.

## Offset

```php
$nairobi->offset();                // Offset, at the clock's now
$nairobi->offset($instant);        // at a given moment
$nairobi->standardOffset();        // when daylight saving is not in effect
$nairobi->dstSavings();            // how far the clock moves at a change
```

`Offset` renders seven ways — `colon`, `compact`, `short`, `gmt`, `utc`, `iso8601`, `seconds` — and
does arithmetic, comparison and conversion to a `DateTimeZone`. It validates to ±18 hours, because
historical local mean times reach −15:56:08 and rejecting them would mean rejecting real data.

## Daylight saving

```php
$zone->isDst($instant);            // at a moment
$zone->observesDst();              // in the current era
$zone->observesDstIn(2026);
$zone->abbreviation($instant);     // 'EAT', 'EDT'
$zone->nextTransition();           // ?Transition
$zone->previousTransition();
$zone->transitionsIn(2026);        // TransitionCollection, UTC-bounded
$zone->transitionsBetween($a, $b);
```

`isDst()` reads the transition record rather than `format('I')`. Both report what the database says,
including for zones with negative daylight saving — `Europe/Dublin` marks its *winter* GMT period as
the saving one — but going through the record also works for zones with no transitions at all, where
the format character would be meaningless rather than absent.

`dstSavings()` never assumes an hour: `Australia/Lord_Howe` shifts 30 minutes and `Antarctica/Troll`
two hours.

## Wall-clock readings

```php
$zone->at($local, GapPolicy $gap, AmbiguityPolicy $ambiguity);  // DateTimeImmutable
$zone->inspect($local);                                          // LocalTime — no exceptions
$zone->isValidLocalTime($local);                                 // bool
$zone->convert($instant);                                        // re-express an instant
```

Given a `DateTimeInterface`, `at()` and `inspect()` read only its wall-clock fields and discard its
zone. Re-reading a local time in a different zone is the point of the call. See
[Daylight saving](../daylight-saving.md).

## Comparison

```php
$newYork->diff($nairobi);              // Offset — how far ahead, at an instant
$newYork->hasSameRulesAs($toronto);    // bool
```

## Output

```php
(string) $zone;          // the identifier
$zone->toArray();        // a flat array, with offset and DST state resolved
$zone->jsonSerialize();
$zone->toDateTimeZone();
```

`dd($zone)` is informative rather than opaque: `__debugInfo()` shows the identifier, kind, offset,
abbreviation, current DST state and next transition, instead of a bare `DateTimeZone`.

## `Transition`

```php
$transition->at;                 // the UTC instant of the change
$transition->offsetBefore;       // both sides — PHP reports only the "after"
$transition->offsetAfter;
$transition->delta();            // signed
$transition->isGap();            // the clock jumped forward
$transition->isOverlap();        // it fell back
$transition->durationSeconds();  // 86400 for Pacific/Apia in 2011
$transition->isDst;
$transition->abbreviation;
```

---

[← Docs index](../../README.md#documentation)
