# Casts and validation

Storing a timezone on a model, and refusing the values that should not reach it.

## The cast

```php
use Simtabi\Laranail\Chrono\Casts\AsTimezone;

protected function casts(): array
{
    return ['timezone' => AsTimezone::class];
}
```

The column stores a canonical identifier; the attribute is a `Timezone` value object.

```php
$user->timezone;                     // Timezone
$user->timezone->offset()->format(); // '+03:00'
```

### It canonicalises on write

That is the reason it exists. This package's resolver deliberately accepts deprecated aliases — that
is what lets `US/Eastern` from a legacy integration resolve at all — so without canonicalisation a
column would end up holding both spellings of one zone.

```php
$user->update(['timezone' => 'Asia/Calcutta']);
$user->getRawOriginal('timezone');   // 'Asia/Kolkata'
```

It accepts anything the resolver does, so a country code, a Windows id or a value object can be
assigned directly:

```php
$user->update(['timezone' => 'KE']);                       // Africa/Nairobi
$user->update(['timezone' => Timezones::of('Asia/Tokyo')]);
```

On a model, `HasTimezone` registers this cast for you and adds the accessors and scopes that go with
it — and yields to an explicit declaration, so `AsTimezone::verbatim()` below still wins. See
[Traits](concerns.md#hastimezone).

`AsTimezone::verbatim()` opts out of the rewrite, for a column that must preserve exactly what was
submitted.

The column is `$table->string('timezone', 64)->nullable()->index();`. No migration ships with the
package — the column belongs to your table.

## Rules

Laravel already ships `timezone`, `timezone:all_with_bc`, `timezone:Africa` and
`timezone:per_country,US`. These cover the cases those cannot express.

### `CanonicalTimezone`

Laravel's bare `timezone` rule validates against `DateTimeZone::ALL`, so it **already rejects**
aliases — `US/Eastern` fails it. And `timezone:all_with_bc` accepts them silently. Neither covers
the middle ground: accept the alias, then insist on the canonical spelling and say which it is.

```php
$request->validate(['timezone' => ['required', new CanonicalTimezone]]);
```

```
The timezone uses a deprecated timezone name. Use Asia/Kolkata instead.
```

| Input | `timezone` | `timezone:all_with_bc` | `CanonicalTimezone` |
|---|:---:|:---:|:---:|
| `America/New_York` | pass | pass | pass |
| `US/Eastern` | fail | pass | fail, naming the canonical form |
| `not a zone` | fail | fail | fail |

### `AllowedTimezone`

Laravel can validate against all 419 identifiers, or a region, or a country. It cannot say "one of
my catalogue" — so a product supporting forty zones still accepts `Antarctica/Troll`, and the picker
and the validator disagree.

```php
new AllowedTimezone;                              // uses the configured catalogue
new AllowedTimezone(['UTC', 'Africa/Nairobi']);   // an explicit list
```

It resolves before checking, so an alias of an allowed zone passes and is normalised rather than
rejected.

### `DateTimeExists` and `DateTimeUnambiguous`

The two highest-value rules, and the ones with no built-in equivalent at all.

Once a year every daylight-saving zone skips an hour and repeats another. A reading inside the gap
names no instant; a reading inside the overlap names two. As *strings* both are perfectly
well-formed, so `date` and `date_format` accept them.

```php
$request->validate([
    'timezone'  => ['required', new CanonicalTimezone],
    'starts_at' => ['required', 'date_format:Y-m-d H:i',
                    new DateTimeExists('timezone'),
                    new DateTimeUnambiguous('timezone')],
]);
```

Both read the zone from another field of the same request — a booking form asks for a time and a
venue timezone together, and neither means anything without the other. With no argument they use the
configured default.

`2026-03-08 02:30` in New York never happened; PHP resolves it to 03:30 EDT without a word, so the
user is shown a confirmation for a time they did not pick.

`2026-11-01 01:30` happened twice. The failure message names both instants so a form can offer them
rather than guessing:

```
The starts_at happened twice in America/New_York (01:30 EDT and 01:30 EST). Please choose which you mean.
```

Neither rule fires when the timezone field is missing or unresolvable. That field carries its own
rule, and reporting one mistake as two helps nobody.

### `TimezoneOffset`

Laravel validates identifiers and has nothing for an offset. Accepts `+03:00`, `-0530`, `+3`,
`GMT+3`, `UTC-5`, `Z`, or a bare count of seconds.

```php
new TimezoneOffset;          // any well-formed offset
TimezoneOffset::inUse();     // and some real zone must currently be on it
```

`inUse()` rejects arithmetically valid but nonexistent values such as `+13:37`.

### `TimezoneAbbreviation`

Validates against the generated 144-entry list, which cannot fall behind the database — it
supersedes the 130-entry hand-maintained whitelist `laranail/validation` carried.

This confirms the string is a real abbreviation; it does not make it an identifier. 96 of the 144
map to more than one zone.

### `TimezoneInCountry`

Laravel's `timezone:per_country,US` fixes the country when the rule is written, so a signup form
where the user picks both country and timezone cannot use it. This reads the country from the
request.

```php
'timezone' => ['required', new TimezoneInCountry('country')],
```

It resolves the zone first, so an alias of a matching zone passes.

## Messages

```bash
php artisan vendor:publish --tag=laranail::chrono-translations
```

That writes to `lang/vendor/laranail-chrono/{locale}/validation.php`. Keys are `timezone`,
`timezone_canonical` and `timezone_allowed`.

---

[← Docs index](../../README.md#documentation)
