# Presentation

A fluent builder that turns a timezone query into whatever the consumer needs — a `<select>`, a JSON
API, a form component, or plain objects.

## Why it is a separate thing

"The timezone list" is not one thing. A `<select>` wants two fields grouped into `<optgroup>`s. A
JSON API wants a flat list of a dozen fields a client can filter and sort on. A validation rule wants
bare identifiers. Building all three from one array either ships eight times the bytes the select
needs, or forces the API client to make a second request for anything the select left out.

So the shape is chosen **last**. Everything before the terminal call describes *what* to show; the
terminal decides *how*.

```php
use Simtabi\Laranail\Chrono\Facades\Chrono;

Chrono::present()
    ->groupByContinent()
    ->withOffset()
    ->forSelect();
```

## Grouping

```php
->groupByContinent()   // Africa, America, Asia, Europe, …
->groupByCountry()     // localised country names
->groupByOffset()      // the +/- view: same clock, regardless of geography
->flat()               // no grouping
->catchAllGroup('Other')
```

`groupByContinent()` buckets on the identifier's leading segment. That is what IANA calls a *region*
and what a picker calls a continent, and the two are not quite the same — `America` spans two
continents, and `Atlantic`, `Indian` and `Pacific` are oceans. It is nonetheless the grouping users
recognise, and the only one derivable from the identifier alone.

Zones with no country — `UTC`, `Etc/*` — fall into the catch-all group rather than vanishing.

## Labels

The template takes `{id} {city} {country} {country_name} {continent} {gmt} {offset} {abbr} {time}
{flag}`.

```php
->label('{city} ({gmt})')                          // Nairobi (UTC +03:00)  — the default
->label('{flag} {city}, {country_name} — {abbr}')  // 🇰🇪 Nairobi, Kenya — EAT
->offsetFormat(OffsetFormat::Short)                // +3 rather than UTC +03:00
->timeFormat('H:i')
->locale('sw')                                     // localised country names; sets dir automatically
->asOf($instant)                                   // offsets and DST evaluated then, not now
```

The flag is computed from the ISO 3166-1 code by shifting its two letters into the Regional
Indicator Symbol block — not a lookup table, so every present and future country works with nothing
to maintain.

`locale()` sets text direction from `locale_is_right_to_left()`, so an Arabic or Hebrew picker gets
`dir="rtl"` without being told twice.

## Choosing fields

Presets cover the common cases:

| Preset | Fields | For |
|---|---:|---|
| `Minimal` | 1 | validation lists, `in:` rules |
| `Select` | 2 | a plain `<select>` |
| `Form` | 8 | a JS form component |
| `Api` | 12 | a client that filters, sorts and badges |
| `Full` | 18 | everything, including fields that cost a transition scan |

```php
->preset(PresentationPreset::Api)
->with(ZoneField::Latitude, ZoneField::Longitude)
->without(ZoneField::Search)
->only(ZoneField::Id, ZoneField::Label)     // discards the preset entirely
```

Shorthands for the fields people reach for by name: `withOffset()`, `withCurrentTime()`,
`withFlag()`, `withCoordinates()`.

Only the requested fields are emitted — a `Select` payload carries two keys, not eighteen nulls.

## Output shapes

```php
->forSelect()          // group => [id => label], or id => label when flat
->forApi()             // list of arrays, grouped or flat
->forFormComponent()   // value / label / group / dir, plus the chosen fields
->forObjects()         // list<PresentedZone>
->forJson()            // a JSON string
->forIdentifiers()     // list<string>
->count()
```

`forObjects()` returns real objects, so an IDE and a static analyser can both see the shape:

```php
$zone = Chrono::present()->preset(PresentationPreset::Api)->forObjects()[0];

$zone->id;                              // 'Africa/Nairobi'
$zone->label();                         // 'Nairobi (UTC +03:00)'
$zone->get(ZoneField::Abbreviation);    // 'EAT'
$zone->has(ZoneField::Flag);            // false, unless you asked for it
```

`PresentedZone::toArray()` returns exactly what `forApi()` produces for the same row, so the object
and JSON views cannot diverge.

## Narrowing the underlying query

`query()` reaches through without leaving the chain. Filters **compose** onto the existing query
rather than replacing it — `inCountry()` accumulates, as it does everywhere else.

```php
Chrono::present()
    ->query(fn ($q) => $q->inRegion(Region::Africa)->observingDst(false))
    ->groupByCountry()
    ->forSelect();
```

## Immutability

Every builder method returns a clone, so a presenter can be stored and branched. Each is
`#[\NoDiscard]`, because `$presenter->withFlag();` as a statement is a silent no-op — the exact
mistake an immutable builder invites.

```php
$base    = Chrono::present()->preset(PresentationPreset::Api);
$grouped = $base->groupByContinent();   // $base is untouched
$flat    = $base->flat();
```

## Where it sits

`Presentation` is its own module, depending on `Timezone` and depended on by nothing. That is why
the accessor is `Chrono::present()` rather than `Timezones::present()`: an accessor on the timezone
module would be a backwards edge, which deptrac rejects.

---

[← Docs index](../../README.md#documentation)
