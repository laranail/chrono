# Converting between zones

"What time is that, over there?" — for one instant or many, in one zone or many.

```php
Chrono::convert('2026-06-15 09:00')->from('Africa/Nairobi')->to('Europe/London')->first();
```

## Why it is a builder and not a function

The question sounds trivial and has three traps in it.

The input may be a **wall-clock reading** rather than an instant, in which case it means nothing
until you name a source zone. That reading may fall in a **daylight-saving gap or overlap**, so it
may name no instant at all, or two. And a caller usually wants **several zones at once** — a meeting
across three offices — which is a fan-out, not three separate calls with three chances to pass a
slightly different instant.

Everything before the terminal describes the question; the terminal decides the shape of the answer.

## One or many, either way round

`of()` and `to()` both take a single value or a list, so a caller never has to choose a form.

```php
Chrono::convert('2026-06-15 09:00')->from('UTC')->to('Asia/Tokyo');
Chrono::convert('2026-06-15 09:00')->from('UTC')->to(['Asia/Tokyo', 'Europe/London']);
Chrono::convert($manyInstants)->to('Asia/Tokyo');
Chrono::convert($manyInstants)->to($manyZones);          // the full grid
Chrono::convert($instant)->toCountry('US');              // every zone of a country
```

Every conversion in one call shares one instant — verified by test — so the results cannot drift
apart the way three separate calls can.

## Interpreting the input

A `DateTimeInterface` is an instant and its own zone is honoured. A string with an offset is already
an instant. A bare wall-clock string is read in the `from()` zone, through the same daylight-saving
policies as everywhere else:

```php
Chrono::convert('2026-03-08 02:30')->from('America/New_York')->onGap(GapPolicy::Throw)->get();
// SkippedLocalTime — that reading never happened
```

## Shapes

```php
->first();      // ?ConvertedTime
->get();        // list<ConvertedTime> — one per input per zone
->keyed();      // identifier => ConvertedTime, for one instant across many zones
->table();      // one row per input, one column per zone — the meeting grid
->forApi();     // list of arrays
->forJson();
->instants();   // the instants alone, before any target zone
```

```php
Chrono::convert(['2026-06-15 09:00', '2026-06-15 14:00'])
    ->from('Africa/Nairobi')
    ->to(['Europe/London', 'Asia/Tokyo'])
    ->format('H:i')
    ->table();

// [['Europe/London' => '07:00', 'Asia/Tokyo' => '15:00'],
//  ['Europe/London' => '12:00', 'Asia/Tokyo' => '20:00']]
```

## `ConvertedTime`

Carries the source instant alongside the local reading, because the two together are the answer:
"09:00 in Nairobi" and "07:00 in London" are the same moment, and a result showing only the local
reading has thrown away the fact that makes it useful.

```php
$converted->instant;          // the moment
$converted->local;            // the same moment, in this zone
$converted->formatted();
$converted->offsetLabel();    // 'UTC +01:00'
$converted->abbreviation();   // 'BST'
$converted->isDst();
$converted->offsetFrom($other);   // seconds — the "they are 7 hours behind" number
```

---

[← Docs index](../../README.md#documentation)