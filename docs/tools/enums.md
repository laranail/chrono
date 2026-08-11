# Enums

Five generated, nine authored — and two ways to name a zone without typing a string.

## Generated

Regenerated from the live tz database and guarded by a byte-for-byte sync check. They carry no
behaviour of their own — each `use`s a hand-written concern — so the emitted text stays a pure
function of tzdata.

| Type | Entries | Contents |
|---|---:|---|
| `Core\Enums\Timezone` | 419 | Every canonical IANA identifier, as a backed enum |
| `Core\Enums\TimezoneLegacy` | 179 | Everything in the backward-compatible list that is not canonical |
| `Core\Enums\TimezoneAbbreviation` | 144 | Every abbreviation PHP knows |
| `Core\Enums\Tz` | 419 | The same identifiers as typed string constants |
| `Enums\TimezoneEnum` | 419 | The `laranail/enumerator` view of the catalogue |

```php
use Simtabi\Laranail\Chrono\Core\Enums\Timezone;

Timezone::AfricaNairobi->value;             // 'Africa/Nairobi'
Timezone::AfricaNairobi->city();            // 'Nairobi'
Timezone::AfricaNairobi->kind();            // TimezoneKind::Canonical
Timezone::AfricaNairobi->canonical();       // follows an alias
Timezone::AfricaNairobi->toDateTimeZone();
Timezone::AfricaNairobi->toTimezone();      // the value object
```

Case names match `laranail/package-tools`' enum exactly, so migrating is a one-line `use` change.

`TimezoneLegacy` mixes three kinds — aliases, fixed offsets and rule-bearing abbreviations — so ask
before assuming:

```php
TimezoneLegacy::from('Asia/Calcutta')->kind();       // Link
TimezoneLegacy::from('Asia/Calcutta')->canonical();  // 'Asia/Kolkata'
TimezoneLegacy::from('Etc/GMT+5')->kind();           // Fixed — no canonical target
TimezoneLegacy::from('CET')->kind();                 // Legacy
```

`TimezoneAbbreviation` exists mainly to make ambiguity visible:

```php
TimezoneAbbreviation::from('CST')->isAmbiguous();     // true
TimezoneAbbreviation::from('CST')->identifiers();     // 62 zones
TimezoneAbbreviation::from('CST')->offsetSeconds();   // null — its uses disagree
TimezoneAbbreviation::from('EAT')->offsetSeconds();   // 10800
TimezoneAbbreviation::from('EDT')->isDaylightSaving();// true
```

## `Tz` — constants, for when you want a string

```php
use Simtabi\Laranail\Chrono\Core\Enums\Tz;

$user->timezone = Tz::AFRICA_NAIROBI;      // 'Africa/Nairobi'
Timezones::of(Tz::AMERICA_NEW_YORK);
```

This exists alongside the enum rather than competing with it, because the two are good at different
things. `Timezone` carries behaviour and is the right parameter type when a method must receive a
real zone. `Tz` entries are plain strings, so they go straight into array keys, `in:` validation
rules, config files, database writes and query builders with no `->value` on the end — and, being
constants, into other constant expressions:

```php
final class Booking
{
    public const string DEFAULT_ZONE = Tz::AFRICA_NAIROBI;
}
```

Behaviour comes from a hand-written concern, so the generated file stays a pure case list:

```php
Tz::all();                         // ['AFRICA_ABIDJAN' => 'Africa/Abidjan', …]
Tz::identifiers();                 // list<string>
Tz::has('Africa/Nairobi');         // true
Tz::nameOf('America/New_York');    // 'AMERICA_NEW_YORK'
Tz::label('America/Argentina/Salta'); // 'Salta'
Tz::enum(Tz::ASIA_TOKYO);          // the behaviour-carrying enum case
Tz::options();                     // identifier => label, for a <select>
Tz::count();                       // 419
```

Labels are derived rather than stored. Baking 419 English strings into a generated file would freeze
a translation that ICU already has in every locale — use `toTimezone()` and the formatter for a
localised name.

## The `laranail/enumerator` bridge

```php
use Simtabi\Laranail\Chrono\Enums\TimezoneEnum;

TimezoneEnum::AfricaNairobi->label();       // 'Nairobi', via the house #[Label] idiom
TimezoneEnum::AfricaNairobi->core();        // the framework-free enum
TimezoneEnum::AfricaNairobi->toTimezone();  // the value object
TimezoneEnum::fromCore(Timezone::AsiaTokyo);
```

`TimezoneEnum` implements `Enumerator` and uses `HasEnumeratorBehavior`, so enumerator's casts,
validation rules and Blade components accept it like any other preset enum.

### Why it is a second enum and not a decorator

Two constraints meet here. `laranail/enumerator` requires `illuminate/*`, and deptrac forbids
`src/Core` from referencing any other `Simtabi\Laranail` package — that boundary is what lets the
whole domain be tested without booting a container. So the bridge cannot live in Core.

Nor can it be an adapter wrapping the Core enum: `Enumerator` is a marker interface whose behaviour
comes from a trait the enum must `use` itself, so a wrapper would satisfy the interface and gain
none of the behaviour.

A parallel enum is the honest shape. Both are generated from one database by one algorithm and both
are covered by the same byte-for-byte sync check, so they cannot drift.

## Authored

| Enum | Purpose |
|---|---|
| `Region` | The top-level identifier segment, with the matching `DateTimeZone` group mask |
| `TimezoneKind` | `Canonical`, `Link`, `Fixed`, `Legacy` |
| `OffsetFormat` | Seven renderings; owns the formatting itself |
| `GapPolicy` | `Forward`, `Backward`, `Throw` |
| `AmbiguityPolicy` | `Earlier`, `Later`, `Throw` |
| `LocalTimeKind` | `Valid`, `Gap`, `Ambiguous` |
| `TimezoneField` | A sortable, groupable, pluckable property |
| `SelectShape` | `Flat`, `Grouped`, `Formed`, `Payload` |
| `NamedFormat` | Machine and human formats |
| `TimeUnit` | Humanisation granularity and thresholds |

`TimezoneField` is one enum rather than separate `SortBy` and `GroupBy` types, because ordering by
offset and grouping by offset need the same accessor — splitting them would mean two `match` blocks
to keep in step.

`OffsetFormat` owns `format()` rather than delegating to a formatter class: the mapping is total,
closed and dependency-free, so a class would be indirection for its own sake.

---

[← Docs index](../../README.md#documentation)
