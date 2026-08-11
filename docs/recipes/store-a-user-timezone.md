# Store a user's timezone

Accept messy input, store one canonical identifier, and render in it.

## The column

```php
$table->string('timezone', 64)->nullable()->index();
```

64 characters covers every identifier with room to spare. The package ships no migration, because
the column belongs to your `users` table.

Then add the trait, and the rest of this page is mostly done for you:

```php
final class User extends Authenticatable
{
    use HasTimezone;
}
```

```php
$user->timezone = 'US/Eastern';     // stored as America/New_York
$user->timezone;                    // a Timezone object
$user->localTime($order->placed_at);
User::query()->whereTimezoneCountry('KE');
```

Canonicalising on write is the reason to bother. The resolver deliberately *accepts* deprecated
aliases — that is what lets `US/Eastern` from a legacy integration resolve at all — but stored
verbatim the column ends up holding `Asia/Calcutta` and `Asia/Kolkata` for one place, and every
`where` and `group by` treats them as two. See [Traits](../tools/concerns.md#hastimezone).

The sections below are what the trait does underneath, and what to reach for when a model is not
involved.

## Validate and canonicalise on the way in

```php
use Simtabi\Laranail\Chrono\Facades\Timezones;

$request->validate([
    'timezone' => ['required', 'string', 'timezone'],   // Laravel's built-in rule
]);

// Store the canonical form so an alias never enters the database.
$user->timezone = Timezones::canonicalise($request->string('timezone')->toString());
```

Laravel's bare `timezone` rule validates against `DateTimeZone::ALL`, so it already rejects
deprecated aliases — `US/Eastern` fails it. That is a fine answer if you never need to accept legacy
input.

If you *do* — a partner posting `US/Eastern`, or `timezone:all_with_bc` on the rule — then
`canonicalise()` is what stops the column holding both `Asia/Calcutta` and `Asia/Kolkata` and the
application treating them as different zones.

To reject aliases outright rather than rewrite them:

```php
'timezone' => ['required', function (string $attribute, mixed $value, Closure $fail): void {
    if (Timezones::canonicalise($value) !== $value) {
        $fail(__('Use :canonical instead.', ['canonical' => Timezones::canonicalise($value)]));
    }
}],
```

## Accepting more than an identifier

A signup form that already knows the country, or a client sending a Windows id, needs no extra work:

```php
$user->timezone = Timezones::resolve($request->input('timezone'));
// 'KE' -> Africa/Nairobi   'Pacific Standard Time' -> America/Los_Angeles
```

`resolve()` throws in strict mode when the input is ambiguous — a bare `US` will not silently become
New York. Offer the alternatives instead:

```php
$candidates = Timezones::lenient()->candidates($input);
```

## Rendering in it

```php
$zone = Timezones::of($user->timezone);

$zone->convert($order->created_at);                 // the same instant, their wall clock
Chrono::format()->format($order->created_at, NamedFormat::MediumDateTime, $zone->zone, $user->locale);
```

Store UTC, convert on display. The package never moves the process default, so `created_at` stays
what Laravel wrote.

## Detecting a sensible default

The browser knows:

```html
<input type="hidden" name="timezone" id="tz">
<script>
    document.getElementById('tz').value = Intl.DateTimeFormat().resolvedOptions().timeZone;
</script>
```

Treat it as untrusted — it is user-controlled — and validate it like any other input. Browsers and
servers ship different tzdata, so a browser can name a zone your PHP does not know; `tryOf()`
returning null is the signal to fall back.

---

[← Docs index](../../README.md#documentation)
