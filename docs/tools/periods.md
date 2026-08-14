# Periods

Spans of time that can be compared, overlapped, subtracted and drawn.

```php
$q1 = Chrono::period()->from('2026-01-01')->to('2026-03-31')->months()->build();

$q1->overlapsWith($booking);                  // do they collide?
$q1->subtract($holidays)->length();           // working days left
Chrono::periods(...$bookings)->gaps();        // when is the room free?
```

## Why a period is not two dates

Two dates in variables answer none of the questions people actually ask. "Does
this booking clash", "when is everyone free", "what is left of the quarter after
these" are all operations on a span, and writing them out by hand is where the
off-by-one lives. A period owns those operations, so the awkward parts, the
endpoints and the rounding, are decided once.

## Precision

Every period measures in a unit, and rounds both ends to it on construction.
That is what makes two periods comparable: "March" and `2026-03-14 09:31:07`
only overlap in a way anyone can reason about once both are rounded the same way.

| Precision | Rounds to |
|---|---|
| `Precision::Year` | `Y-01-01 00:00:00` |
| `Precision::Month` | `Y-m-01 00:00:00` |
| `Precision::Day` | `Y-m-d 00:00:00` |
| `Precision::Hour` | `Y-m-d H:00:00` |
| `Precision::Minute` | `Y-m-d H:i:00` |
| `Precision::Second` | `Y-m-d H:i:s` |

Comparing periods of different precisions throws rather than guessing. Rounding
one to the other would decide the answer, and which way it went would not be
visible at the call site:

```php
$days->overlapsWith($months);
// InvalidPeriod: Periods measured in day and month cannot be compared.
```

## Boundaries

Boundaries decide whether a period's own endpoints belong to it, which is the
difference between two things colliding and merely meeting.

```php
$morning = Chrono::period()->from('09:00')->to('10:00')->hours()->build();
$later   = Chrono::period()->from('10:00')->to('11:00')->hours()->build();

$morning->overlapsWith($later);   // true — 10:00 belongs to both

$exclusive = Chrono::period()->from('09:00')->to('10:00')->hours()->excludingEnd()->build();

$exclusive->overlapsWith($later); // false
$exclusive->touchesWith($later);  // true
```

Neither answer is universally right. A hotel night and a meeting room want
different ones, so the choice is explicit.

| Builder | Enum | Meaning |
|---|---|---|
| `includingAll()` | `Boundaries::IncludeAll` | Both endpoints belong to the period (the default) |
| `excludingStart()` | `Boundaries::ExcludeStart` | The span begins one step after `from()` |
| `excludingEnd()` | `Boundaries::ExcludeEnd` | The span ends one step before `to()` |
| `excludingAll()` | `Boundaries::ExcludeAll` | Neither endpoint belongs |

## Building one

The builder names the two things a raw constructor leaves as trivia:

```php
Chrono::period()->from('2026-01-01')->to('2026-12-31')->days()->build();
Chrono::period()->from($start)->lasting(7)->days()->build();
Chrono::period()->from($start)->lasting(3)->months()->excludingEnd()->build();
```

`lasting()` counts the starting step, so `lasting(7)->days()` is seven days
including the first. A builder is mutable while being filled in and
`Chrono::period()` hands back a fresh one each call, so two call sites cannot
overwrite each other.

`Period::make()` and `new Period()` remain available when the four arguments are
already to hand.

## Asking about one period

```php
$period->startsBefore($date);      $period->startsBeforeOrAt($date);
$period->startsAfter($date);       $period->startsAfterOrAt($date);
$period->startsAt($date);
$period->endsBefore($date);        $period->endsBeforeOrAt($date);
$period->endsAfter($date);         $period->endsAfterOrAt($date);
$period->endsAt($date);

$period->contains($date);          // a moment
$period->contains($other);         // or a whole period
$period->overlapsWith($other);
$period->touchesWith($other);
$period->equals($other);
```

## Operating on periods

| Call | Returns |
|---|---|
| `overlap(...$others)` | The span shared by all of them, or `null` |
| `overlapAny(...$others)` | Each overlap separately, as a collection |
| `subtract(...$others)` | What is left, as a collection |
| `gap($other)` | The span between them, or `null` if they meet or overlap |
| `diffSymmetric($other)` | What belongs to one but not both |
| `renew()` | The same length again, starting where this ended |

```php
$quarter->subtract($holiday, $shutdown);   // PeriodCollection of working spans
$a->gap($b);                               // null when they touch
$subscription->renew();                    // the next term
```

## Measuring

```php
$period->length();          // whole steps of its precision, endpoints included
$period->duration();        // a PeriodDuration
$period->moments();         // every step, as DateTimeImmutable
$period->includedStart();   // the first moment that belongs to it
$period->includedEnd();     // the last
$period->ceilingEnd();      // the first moment after it
```

`PeriodDuration` compares only within one precision. "Three months" against
"ninety days" needs a calendar and a starting point, neither of which a duration
has, so it is not pretended otherwise:

```php
$a->duration()->isLongerThan($b->duration());
$a->duration()->inSteps();
(string) $a->duration();     // '31 days'
```

## Collections

`PeriodCollection` is where the interesting questions live. Every operation
returns a new collection, so a chain never disturbs what it read.

```php
$free = Chrono::periods(...$alice)->overlapAll(Chrono::periods(...$bob));
```

| Call | Returns |
|---|---|
| `overlapAll(...$collections)` | What every collection has in common |
| `subtract(...$periodsOrCollections)` | What is left of each period here |
| `boundaries()` | One period spanning everything, gaps included |
| `gaps()` | The holes between them |
| `intersect($window)` | Each period clipped to a window |
| `union()` | Overlapping and touching spans merged |
| `sorted()` | In start order |
| `map()` `filter()` `reduce()` | The usual, returning collections |
| `first()` `last()` `count()` `length()` `isEmpty()` | Accessors |

It is `Countable`, `IteratorAggregate` and `JsonSerializable`, so it behaves in
a `foreach`, a `count()` and a JSON response without conversion.

## Seeing it

Overlap bugs are hard to read as dates and obvious as bars:

```php
echo Chrono::visualize(40)->visualize([
    'sales'   => $sales,
    'support' => $support,
    'free'    => Chrono::periods($sales, $support)->gaps(),
]);
```

```
sales   [==========]
support        [==========]
free               [=]
```

Meant for a test failure or a `dd()`, where seeing that two spans touch rather
than overlap takes a second instead of a minute.

---

[← Docs index](../../README.md#documentation)
