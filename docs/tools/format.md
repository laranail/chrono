# Formatting and parsing

Rendering an instant for a locale, and reading a string back — with the two traps in PHP's own
parsing closed.

## Two families of format

`NamedFormat` splits deliberately, because machine and human formats must behave differently.

**Machine formats** are fixed patterns and never localise. An ISO 8601 timestamp is the same string
in Cairo as in Tokyo; localising one is how a value ends up in a column nothing can read back.

```php
NamedFormat::Iso8601   // 2026-06-15T14:30:00+00:00
NamedFormat::Rfc2822   NamedFormat::Rfc3339   NamedFormat::Atom   NamedFormat::Cookie
NamedFormat::Sql       // 2026-06-15 14:30:00
NamedFormat::SqlDate   NamedFormat::SqlTime   NamedFormat::Timestamp
```

**Human formats** resolve to an ICU *skeleton*, which names the fields wanted rather than their
order. `IntlDatePatternGenerator` supplies the pattern each locale actually uses:

```php
Chrono::format()->format($when, NamedFormat::MediumDate, locale: 'en_US'); // Jun 15, 2026
Chrono::format()->format($when, NamedFormat::MediumDate, locale: 'de_DE'); // 15. Juni 2026
Chrono::format()->format($when, NamedFormat::MediumDate, locale: 'ja_JP'); // 2026年6月15日
```

Hard-coding `M j, Y` instead gives every locale American ordering with translated month names.

Available: `ShortDate`, `MediumDate`, `LongDate`, `FullDate`, `ShortTime`, `MediumTime`,
`ShortDateTime`, `MediumDateTime`, `LongDateTime`, `DayMonth`, `MonthYear`, `WeekdayDate`.

## Formatting

```php
$formatter = Chrono::format();

$formatter->format($instant, NamedFormat::MediumDate, $zone, $locale);
$formatter->format($instant, 'Y/m/d');            // an unrecognised string is a raw PHP pattern
$formatter->raw($instant, 'Y-m-d', $zone);        // explicit, never localised
$formatter->skeleton($instant, 'yMMMd', $zone, $locale);
$formatter->all($instant);                        // every named format, for a debug panel
```

The zone is applied before formatting, so passing one re-expresses the instant rather than
relabelling it.

## Parsing

```php
$parser = Chrono::parse();

$parser->parse($value, $zone, $gap, $ambiguity);   // DateTimeImmutable
$parser->tryParse($value, $zone);                  // ?DateTimeImmutable
$parser->parseFormat($value, NamedFormat::SqlDate, $zone);
$parser->lenient();                                // a copy that converts instead of refusing
```

### The `$timezone` argument PHP ignores

`createFromFormat()` documents its `$timezone` parameter as a default for strings that carry no
zone — and silently discards it when they do. Nothing warns you.

```php
DateTimeImmutable::createFromFormat(
    'Y-m-d H:i:sP', '2026-06-01 12:00:00+05:00', new DateTimeZone('America/New_York')
)->format('P');   // '+05:00'
```

Strict parsing refuses the contradiction and names the offset in the exception context. Lenient
parsing converts to the requested zone, preserving the instant:

```php
$parser->parse('2026-06-01 12:00:00+05:00', $newYork);            // UnparsableDateTime
$parser->lenient()->parse('2026-06-01 12:00:00+05:00', $newYork); // -04:00, same instant
```

### Wall-clock strings go through the DST policies

A string with no offset is a local reading, so it is resolved with the same gap and ambiguity
handling as `Timezone::at()` rather than inheriting PHP's silent resolution.

```php
$parser->parse('2026-03-08 02:30', $newYork, GapPolicy::Throw);  // SkippedLocalTime
```

### Fields are reset, not inherited

`parseFormat()` prefixes the pattern with `!`, so a date-only format yields midnight rather than
quietly picking up the current time of day.

```php
$parser->parseFormat('2026-06-15', NamedFormat::SqlDate)->format('H:i:s');  // 00:00:00
```

## A note on serialisation

`json_encode(new DateTimeImmutable(...))` emits a three-key object, not an ISO 8601 string — Carbon
only looks right because it implements `JsonSerializable`. Format explicitly before serialising.

---

[← Docs index](../../README.md#documentation)
