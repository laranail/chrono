# Resolution

Turning whatever an application is handed into a canonical identifier — and refusing when the input
genuinely does not name one.

## The chain

Nine strategies, tried in order, first confident answer wins.

| Strategy | Accepts | Confidence |
|---|---|:---:|
| `instance` | `Timezone`, `DateTimeZone`, `IntlTimeZone`, `DateTimeInterface` | 1.0 |
| `identifier` | An exact IANA name, case-insensitively | 1.0 |
| `alias` | A deprecated name — `Asia/Calcutta`, `US/Eastern` | 1.0 |
| `offset` | `+03:00`, `-0530`, `+3`, `GMT+3`, `UTC-5`, `Z`, seconds | 1.0 |
| `windows` | `Pacific Standard Time`, `E. Africa Standard Time` | 0.95 |
| `country` | `KE`, `US` | 0.9 or refuses |
| `locale` | `en_KE`, `sw-KE` | inherited |
| `abbreviation` | `EAT`, `EST` — off by default | 0.2–0.9 |
| `city` | `Nairobi`, `new york`, `são paulo` | 0.4–0.8 |

Anything that *wraps* a string is unwrapped before the chain runs: a string-backed enum case or a
`Stringable` becomes the string it spells, and that string is then judged like any other. A
`Timezone` is exempt — it carries a zone rather than spells one, and `instance` reads it exactly.

Unwrapping rather than short-circuiting is the point. A strategy that answered "this is a `Timezone`
enum case, so it is valid" would skip validation entirely, and the same shortcut on
`TimezoneAbbreviation::CST` would assert that `CST` names one zone when it names sixty-two.

## Order is load-bearing

`identifier` must precede `abbreviation`, because `EST` is simultaneously a real identifier in the
backward-compatible list and an abbreviation for 41 zones — checking the list first means the exact
match wins.

`alias` must follow `identifier`, and `identifier` searches canonical names first, falling back to
the self-canonical set (`EST`, `CET`, `Etc/*`) only afterwards. Without that split, `Asia/Calcutta`
matches as an identifier and is handed straight back, and the alias never gets a chance — which is
exactly how two spellings of one zone end up in a database.

## Membership, not construction

Validity is always membership in `listIdentifiers()`, never "the `DateTimeZone` constructor did not
throw". `CEST`, `+03:00`, `GMT+3` and `Z` all construct successfully and appear in no identifier
list at all.

## Ambiguity is reported, not resolved

```php
Timezones::of('US');   // throws — the United States has 29 zones
```

Picking one for a user is a bug waiting to be filed, so strict mode refuses. Lenient mode answers
with low confidence and the alternatives attached:

```php
$resolution = Timezones::lenient()->explain('US');

$resolution->identifier;    // a candidate
$resolution->confidence;    // < 0.5
$resolution->alternatives;  // all 29, for a picker to offer
$resolution->via;           // 'country'
```

`Timezones::candidates($input)` returns the same set as a collection.

To decide once, in configuration:

```php
'resolution' => [
    'country_defaults' => ['US' => 'America/New_York'],
],
```

Nothing is guessed on your behalf; the map ships empty.

### Abbreviations

Off by default, because **96 of the 144 abbreviations PHP knows map to more than one zone** — `CST`
matches 62. With them on, a country bias decides:

```php
Timezones::preferring('US')->allowingAbbreviations()->of('CST');  // America/Chicago
```

Without a bias, the first candidate is returned at confidence 0.2 with every alternative attached,
so a caller can prompt rather than assume.

## Canonicalising, and not

`alias` answers with the zone a deprecated name points at, so `Asia/Calcutta` in gives `Asia/Kolkata`
out. That is what stops one place accumulating two spellings across a table, and it is the default.

Turn it off — `resolution.canonicalise = false`, or `Timezones::preservingAliases()` — and an input
that is itself a usable identifier comes back as written, while everything else still resolves
normally. The zone still knows what it points at and still compares equal to it:

```php
$zone = Timezones::preservingAliases()->of('Asia/Calcutta');

$zone->identifier;             // 'Asia/Calcutta'
$zone->canonicalIdentifier();  // 'Asia/Kolkata'
$zone->equals('Asia/Kolkata'); // true
```

Only an exact alias is preserved. An abbreviation, a country code or a Windows name has no identity
of its own to keep. `Timezones::canonicalise()` is unaffected either way — that is what its name
promises.

## Offsets are not places

Resolving `+03:00` gives a fixed-offset zone rather than a city that shares the offset today. A
city's offset is a fact about a date; an offset is not a place. The resulting zone has no rules —
`getTransitions()` on it returns `false`, which the scanner normalises.

## Narrowing the chain

```php
'resolution' => [
    'strategies' => ['identifier', 'alias', 'offset'],
],
```

Anything omitted is simply not attempted, so a strict API can accept identifiers and offsets and
nothing else.

---

[← Docs index](../../README.md#documentation)
