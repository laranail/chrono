# Querying

A fluent, immutable builder over the catalogue.

Every builder method returns a clone, so a query can be stored and branched without one caller's
filter leaking into another's. Each is marked `#[\NoDiscard]`, because `$query->inCountry('KE');` as
a statement is a silent no-op — the mistake an immutable builder invites.

```php
Timezones::query()
    ->inRegion(Region::Africa)
    ->offsetBetween('+01:00', '+04:00')
    ->observingDst(false)
    ->orderByOffset()
    ->take(20)
    ->get();
```

## Filters

| Method | Notes |
|---|---|
| `inRegion(Region\|string ...)` | |
| `inCountry(string ...)` | ISO 3166-1 alpha-2 |
| `withOffset(int\|string ...)` | Accepts any spelling the offset parser does |
| `offsetBetween($min, $max)` | Inclusive |
| `withAbbreviation(string ...)` | |
| `observingDst(bool)` | Whether the zone observes daylight saving in the current era |
| `currentlyInDst(bool)` | Whether it is in effect now, or at `asOf()` |
| `matching(string)` | Case-insensitive substring over identifier, city and country |
| `only(string ...)` / `except(string ...)` | |
| `where(callable)` | `fn (Timezone $t): bool` |
| `includeDeprecated(bool)` | Adds the 128 aliases |
| `includeFixed(bool)` | Adds `Etc/*` |
| `includeUtc(bool)` | |

Deprecated aliases and `Etc/*` are excluded by default, so a picker cannot list one zone twice under
two names.

Filters run cheapest-first: string tests on the identifier, then index lookups, then anything that
computes an offset, then your callbacks.

## Ordering and slicing

```php
->orderBy(TimezoneField $field, bool $descending = false)
->orderByOffset()
->orderByIdentifier()
->skip(int)
->take(int)
```

Ordering breaks ties on the identifier, so two zones sharing an offset keep a stable relative order
between runs.

`skip()` and `take()`, never `offset()` and `limit()` — `Offset` is already a UTC-offset value
object here, and a query method of the same name would be a permanent source of confusion.

## Evaluation context

```php
->asOf(DateTimeInterface $instant)
```

Offsets and daylight-saving state are evaluated at that instant rather than now. Without it, the
injected clock supplies the moment.

## Terminals

```php
->get();            // TimezoneCollection
->lazy();           // Generator, materialising a Timezone only when a predicate needs one
->first();          // ?Timezone
->firstOrFail();    // Timezone
->exists();         // bool
->count();          // int
->identifiers();    // list<string>
->groupBy(TimezoneField);
->toSelectOptions(SelectShape, bool $rtl = false);
->toArray();
```

`count()` respects `skip()` and `take()`, so it always agrees with `get()->count()`. SQL builders
traditionally have `COUNT` ignore `LIMIT`; here that would mean `take(5)->count()` returning 419,
which nobody expects.

## Select shapes

Three reproduce the output `simtabi/pheg` emitted, byte for byte, so a migrating caller gets the
same arrays.

```php
SelectShape::Flat
// ['Africa/Nairobi' => 'Nairobi, KE (UTC +03:00)']

SelectShape::Grouped
// ['Africa' => ['Africa/Nairobi' => 'Nairobi (UTC +03:00)']]

SelectShape::Formed
// ['Africa' => ['Africa/Nairobi' => 'Africa/Nairobi (UTC +03:00)']]

SelectShape::Payload
// [['id' => 'Africa/Nairobi', 'label' => …, 'offset' => 10800, 'search' => 'nairobi ke eat +03:00',
//   'dir' => 'ltr', …]]
```

`Payload` is the one that package lacked: an array of objects with a pre-lowercased `search` token
and a text direction, which is what a JavaScript component actually needs.

## The collection

`TimezoneCollection` is immutable, keyed by identifier, and iterable.

```php
$collection->all();         $collection->identifiers();
$collection->get($id);      $collection->has($id);
$collection->first();       $collection->last();
$collection->filter($fn);   $collection->reject($fn);   $collection->map($fn);
$collection->sortBy(TimezoneField::Offset);
$collection->take(10);      $collection->skip(5);
$collection->groupBy(TimezoneField::Region);
$collection->pluck(TimezoneField::City);
$collection->toSelectOptions(SelectShape::Grouped);
```

---

[← Docs index](../../README.md#documentation)
