# Temporal value types

Types PHP does not have, for the questions `DateTimeImmutable` cannot answer without lying.

## Why they exist

`DateTimeImmutable` is an *instant* — a point on a timeline, which necessarily belongs to a zone.
Many things people store in one are not instants at all.

A birthday has no zone. Carried as a UTC midnight and converted for a user in Honolulu, it lands on
the previous day — which is how a date shifts by one for half your users. A `LocalDate` has no
instant, so it cannot move.

The same reasoning gives the rest: opening hours are a time with no date, a billing period is a
month with no day, an anniversary is a day with no year, and an elapsed length is seconds with no
calendar at all.

## `LocalDate`

```php
LocalDate::of(2026, 6, 15);
LocalDate::parse('2026-06-15');
LocalDate::fromDateTime($instant);   // reads the date fields; discards the zone
```

It refuses dates that do not exist — `LocalDate::of(2026, 2, 30)` throws, and 29 February is
accepted only in a leap year.

**Month arithmetic clamps rather than overflowing.** 31 January plus one month is 28 or 29 February.
PHP's own answer is 3 March:

```php
LocalDate::of(2026, 1, 31)->plusMonths(1);            // 2026-02-28
(new DateTimeImmutable('2026-01-31'))->modify('+1 month');  // 2026-03-03
```

That overflow is arithmetically defensible and almost never what a billing cycle means.

**The ISO week-numbering year is not the calendar year.** `2027-01-01` is week 53 of *2026*.
Grouping a report by `format('W')` and `format('Y')` separately invents a phantom week in the wrong
year; `isoWeek()` returns the pair that agrees:

```php
LocalDate::of(2027, 1, 1)->isoWeek();   // ['year' => 2026, 'week' => 53]
```

It becomes an instant only when you name a zone:

```php
$date->atStartOfDay($zone);
$date->atTime(TimeOfDay::of(9), $zone);
```

## `TimeOfDay`

A time with no date and no zone — opening hours, a daily reminder, a shift start.

```php
TimeOfDay::of(9, 30);
TimeOfDay::parse('09:30');
TimeOfDay::of(23, 30)->plusHours(1);   // 00:30 — wraps
```

Ranges that cross midnight work, because that is the common case for opening hours rather than an
edge one:

```php
TimeOfDay::of(1, 0)->isBetween(TimeOfDay::of(23, 0), TimeOfDay::of(2, 0));   // true
```

> Named `TimeOfDay` rather than `LocalTime` because this package already has a
> [`LocalTime`](timezone.md) — the result of resolving a wall-clock reading against a zone's
> daylight-saving rules. Two types with one name meaning different things is a trap.

Note "09:00 every day" is not a fixed number of seconds from midnight in a zone that observes
daylight saving. This type stays deliberately ignorant of that; combine it with a `LocalDate` and a
zone, and the resolution happens where the [policies](../daylight-saving.md) live.

## `YearMonth`

A billing period, a card expiry, a reporting bucket.

```php
YearMonth::of(2026, 6);
YearMonth::parse('2026-06');
YearMonth::of(2026, 1)->plusMonths(13);        // 2027-02
YearMonth::of(2024, 2)->length();              // 29
YearMonth::of(2026, 1)->monthsUntil($other);
```

With no day, "the month after January" cannot be confused with "31 January plus a month".

## `MonthDay`

An anniversary or a recurring holiday. 29 February is valid here and has no year to be valid in,
which is the whole reason the type exists.

```php
$leapDay = MonthDay::of(2, 29);

$leapDay->existsIn(2026);            // false
$leapDay->inYear(2024);              // 2024-02-29
$leapDay->inYear(2026);              // 2026-02-28
$leapDay->inYear(2026, 'later');     // 2026-03-01
```

There is no correct answer for a leap-day anniversary in a common year — some systems observe it on
28 February, others on 1 March — so `inYear()` makes you choose rather than quietly picking.

## `Duration`

An elapsed length of time, in seconds. **Deliberately not `DateInterval`**, which mixes elapsed units
with calendar ones and so cannot say how long something was without knowing where it started:

```php
$from = new DateTimeImmutable('2026-03-08 00:00', $newYork);
$to   = new DateTimeImmutable('2026-03-09 00:00', $newYork);

$from->diff($to)->d;                    // 1 — "one day"
Duration::between($from, $to)->hours(); // 23 — what the clock actually advanced
```

```php
Duration::ofHours(1)->plus(Duration::ofMinutes(30))->toClockString();  // 1:30:00
Duration::parse('PT1H30M')->seconds;                                   // 5400
Duration::ofDays(3)->toClockString();                                  // 72:00:00 — hours do not wrap
```

`parse()` accepts only the elapsed ISO 8601 designators. `P1M` is **rejected**: a month is not a
length of time until you know which month, and silently treating it as 30 days is how a subscription
ends on the wrong date.

## `DayOfWeek` and `Month`

Int-backed, because ISO 8601 defines them numerically.

```php
DayOfWeek::Monday->value;      // 1 — ISO: Monday is 1, Sunday is 7
DayOfWeek::fromPhp(0);         // Sunday — PHP's own 'w' format counts Sunday as 0
DayOfWeek::Sunday->toPhp();    // 0

Month::February->length(2024); // 29
Month::February->length(1900); // 28 — a century, so not a leap year
Month::June->quarter();        // 2
```

`fromPhp()` and `toPhp()` exist because the two conventions disagree and a bare integer does not say
which one it follows.

---

[← Docs index](../../README.md#documentation)
