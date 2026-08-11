# Resolve messy input

Accept whatever an integration sends, and refuse cleanly when it does not name one zone.

```php
use Simtabi\Laranail\Chrono\Facades\Timezones;

Timezones::of('Africa/Nairobi');         // an identifier
Timezones::of('Asia/Calcutta');          // Asia/Kolkata — a deprecated alias
Timezones::of('Pacific Standard Time');  // America/Los_Angeles — from a .NET client or Outlook
Timezones::of('+03:00');                 // a fixed-offset zone
Timezones::of('KE');                     // a country with one zone
Timezones::of('en_KE');                  // a locale
Timezones::of('nairobi');                // a city
Timezones::of($request->date('at'));     // anything already carrying a zone
```

## When it will not answer

```php
Timezones::of('US');       // throws — 29 zones, and none of them is "the" American one
Timezones::of('CST');      // throws — off by default, and 62 zones use it
```

Both refusals are deliberate. Show the alternatives:

```php
$resolution = Timezones::lenient()->explain($input);

if ($resolution?->isAmbiguous()) {
    return response()->json([
        'message'    => 'That could mean more than one timezone.',
        'candidates' => Timezones::lenient()->candidates($input)->identifiers(),
    ], 422);
}
```

Or decide once, in `config/laranail/chrono.php`:

```php
'resolution' => [
    'country_defaults'    => ['US' => 'America/New_York', 'AU' => 'Australia/Sydney'],
    'preferred_countries' => ['US', 'GB'],
    'abbreviations'       => true,
],
```

With that, `US` resolves to New York and `CST` to Chicago — because you said so, not because the
package guessed.

## Understanding an answer

```php
$resolution = Timezones::explain('Pacific Standard Time');

$resolution->identifier;    // 'America/Los_Angeles'
$resolution->via;           // 'windows'
$resolution->confidence;    // 0.95
$resolution->alternatives;  // []
```

Useful for logging what an integration actually sent, and for spotting a partner that has been
posting abbreviations all along.

## Narrowing what you accept

A public API usually should not accept city names.

```php
'resolution' => [
    'strategies' => ['instance', 'identifier', 'alias', 'offset'],
],
```

Anything omitted is not attempted.

---

[← Docs index](../../README.md#documentation)
