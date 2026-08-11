# Traits

Eight traits that give a class the package's behaviour in one `use` line — and keep working when
there is no framework underneath.

## The problem they solve

Injecting `Timezones` into a constructor is the right thing and is not always available. A mailable,
an Eloquent model, a Blade component, a queue job resolved by name, a value object, a console script
run outside the framework — all of these either have no constructor you control or no container to
resolve one from.

The usual answer is to reach for `app(Timezones::class)`, which fails outside Laravel, or to build a
`new Timezones` inline, which works everywhere and is worse: inside an application it silently opts
out of that application's daylight-saving policy, catalogue restrictions and display settings. A zone
resolved in that helper can then disagree with the same zone resolved anywhere else in the same
request, and nothing surfaces it.

These traits resolve through the container when there is one, and construct working defaults when
there is not. One `use` line is correct in both.

```php
use Simtabi\Laranail\Chrono\Core\Concerns\InteractsWithChrono;

final class ShiftRoster
{
    use InteractsWithChrono;

    public function summarise(Shift $shift, string $viewerZone): string
    {
        $starts = $this->atLocal($shift->local_start, $shift->timezone);

        return $this->formatDate($this->inZone($starts, $viewerZone))
            . ' (' . $this->humanizeDate($starts) . ')';
    }
}
```

No constructor, no binding, no configuration. The same class works in a plain script.

## The set

| Trait | Namespace | Gives |
|---|---|---|
| `InteractsWithClock` | `Core\Concerns` | `now()`, `timestamp()`, `withClock()` |
| `InteractsWithTimezones` | `Core\Concerns` | `zone()`, `tryZone()`, `zoneIdentifier()`, `nowInZone()`, `inZone()`, `zoneQuery()`, `zonesInCountry()` |
| `ResolvesLocalTimes` | `Core\Concerns` | `atLocal()`, `inspectLocal()`, `localTimeExists()`, `localTimeIsAmbiguous()` |
| `ConvertsTimezones` | `Core\Concerns` | `convertTime()`, `convertOne()` |
| `PresentsTimezones` | `Core\Concerns` | `presentZones()`, `zoneOptions()`, `zoneIdentifiers()` |
| `RendersDateTimes` | `Core\Concerns` | `formatDate()`, `formatDateSkeleton()`, `humanizeDate()`, `humanizeDuration()` |
| `InteractsWithChrono` | `Core\Concerns` | all six |
| `HasTimezone` | `Models\Concerns` | an Eloquent model that belongs to a zone |
| `FreezesChronoTime` | `Testing` | stop the clock in a test |

Prefer a single trait when a class needs one thing. The narrow one states the dependency, and a class
that pulls in six services to format one date tells the next reader something false.

## `InteractsWithClock`

`new DateTimeImmutable()` inside a class is untestable by construction: a test for "this renews next
month" either waits a month or is really testing today's arithmetic.

```php
final class RenewalService
{
    use InteractsWithClock;

    public function renewsOn(Subscription $subscription): DateTimeImmutable
    {
        return $this->now()->modify('+1 month');
    }
}

$service->withClock(new FrozenClock('2026-01-31T09:00:00Z'))->renewsOn($subscription);
```

Freezing the class's clock freezes it **everywhere the other traits reach**, because they compose
this one — `nowInZone()`, `humanizeDate()` and every offset lookup read the same clock. That is the
whole reason the composition exists rather than six independent traits.

## `InteractsWithTimezones`

```php
$this->zone($input);                    // Timezone, or throws
$this->tryZone($input);                 // ?Timezone
$this->zoneIdentifier($input);          // the canonical string, for a column
$this->nowInZone('Asia/Tokyo');
$this->inZone($instant, $user->timezone);
$this->zoneQuery();                     // narrowed to the configured catalogue
$this->zonesInCountry('KE');
```

Every method takes the same range of input the service does — a string, an enum case, a
`DateTimeZone`, a `Timezone`, anything `Stringable` — so a caller holding `$user->timezone` never has
to know which of those it is.

## `ResolvesLocalTimes`

For a class that turns a date and a time a human typed into an instant. Twice a year that is not a
question with one answer.

```php
final class BookingImporter
{
    use ResolvesLocalTimes;

    protected function dstPolicy(): DstPolicy
    {
        return DstPolicy::strict();     // an import must not invent a time
    }

    public function import(array $row): DateTimeImmutable
    {
        return $this->atLocal($row['starts_at'], $row['timezone']);
    }
}
```

Leave `dstPolicy()` alone and the [configured pair](../configuration.md#daylight-saving) applies.
`inspectLocal()` is the branch-instead-of-catch form, which is what a form wants: a user who has hit
a real ambiguity in their own calendar has not made a mistake, and an exception is the wrong shape
for asking them which one they meant.

## `ConvertsTimezones`

```php
$this->convertTime($meeting->starts_at)->to($attendees->pluck('timezone'))->keyed();
$this->convertOne($instant, $user->timezone);
```

Every conversion from one builder shares one instant by construction — writing the fan-out as five
separate calls gives five chances to pass a slightly different one.

## `PresentsTimezones`

```php
$this->presentZones()->locale($locale)->forApi();
$this->zoneOptions();          // the configured picker shape
$this->zoneIdentifiers();      // for an `in:` rule or an export
```

The presenter already carries this application's catalogue, so a controller cannot offer zones the
validation rules will then reject.

## `RendersDateTimes`

```php
$this->formatDate($invoice->due_at, NamedFormat::LongDate, $recipientLocale);
$this->formatDateSkeleton($instant, 'yMMMd', 'de_DE');
$this->humanizeDate($comment->created_at, 'ar');
$this->humanizeDuration($seconds, 'ru', parts: 2);
```

Both calls look trivial and are not. `format('M j, Y')` is English forever no matter who reads it,
and "3 days ago" has six plural forms in Arabic which `trans_choice`'s pipe syntax cannot express.
Machine formats — `Iso8601`, `Rfc3339`, `Sortable` — never localise, which is why they are named
separately.

## `HasTimezone`

For a model that belongs to a zone: a user, a venue, a tenant, a store. The column is on a table this
package does not own, so add `string('timezone')->nullable()` yourself.

```php
final class User extends Authenticatable
{
    use HasTimezone;
}
```

```php
$user->timezone;                        // a Timezone object
$user->timezone = 'US/Eastern';         // stored as America/New_York
$user->timezoneOrDefault();             // never null, so never the server's zone by accident
$user->localNow();
$user->localTime($order->placed_at);
$user->localTime('created_at');         // an attribute name works too
$user->timezoneOffsetFrom('Europe/Berlin');
```

**Canonicalising on write is the point.** The resolver deliberately *accepts* deprecated aliases —
that is what lets `US/Eastern` from a legacy integration resolve at all — but stored verbatim a
column ends up holding `Asia/Calcutta` and `Asia/Kolkata` for the same place, and every `where` and
`group by` treats them as two.

The cast is registered by the trait and yields to an explicit declaration, so a model that already
casts the column with `AsTimezone::verbatim()` keeps what it declared. Override the column name with
`protected string $timezoneColumn = 'tz';`.

### Scopes

```php
User::query()->whereTimezone('US/Eastern');        // finds rows stored as America/New_York
User::query()->whereTimezoneIn([$a, $b]);
User::query()->whereTimezoneCountry('KE', 'JP');   // no country column needed
User::query()->whereTimezoneObservesDst(false);
```

`whereTimezone()` matches canonically, which a plain `where` on the column does not.

## `FreezesChronoTime`

```php
final class RenewalTest extends TestCase
{
    use FreezesChronoTime;

    public function test_it_renews_next_month(): void
    {
        $this->freezeChronoTime('2026-01-31T09:00:00Z');

        $this->assertSame('2026-02-28', $subscription->renewsOn()->format('Y-m-d'));
    }
}
```

It replaces the container's clock **and rebuilds the singletons already made from it**, which is the
part hand-rolled freezing misses: a service resolved before the freeze keeps reading the wall clock.

`travelChronoTo()` moves it. `freezeAtNextTransition('Europe/London')` freezes at the moment a
daylight-saving change takes effect — asked of the database rather than hard-coded, because the dates
move every year and a hard-coded one silently stops testing anything.

This package's own `TestCase` uses it. Freezing by hand there would mean the shipped helper was never
exercised.

## Overriding what a trait resolves

Every trait takes an explicit service, and the explicit one always wins:

```php
$service->withTimezones($restricted);      // clones — safe on a shared instance
$service->withClock($frozen);
$service->withDstPolicy(DstPolicy::strict());
$service->withFormatter($formatter);
$service->withHumanizer($humanizer);
$service->withDisplayOptions($display);
$service->withSelectOptions($options);

$service->setClock($frozen);               // mutates, for a class assembled once
$service->setTimezones($timezones);
```

Each `with…()` is `#[\NoDiscard]`, because `$service->withClock($frozen);` as a statement is a silent
no-op — the exact mistake an immutable API invites.

## Two constraints

The traits hold their resolved services in private properties, so **they cannot be used by a
`readonly` class**. Inject the services instead; that is what the classes in this package do.

Outside a framework there is no configuration to read, so the defaults apply: `forward`/`earlier` for
daylight saving, the full 419-zone catalogue, `UTC +03:00` offsets. Hand the class a configured
service if a script needs to match an application.

## How it finds the container

`Core\Support\ServiceResolver` holds a closure the service provider installs at boot. Without it every
lookup returns null and the trait constructs its own default; nothing in `Core` learns what a
container is, which is what keeps the boundary that
[deptrac enforces](../architecture.md) intact.

It is the only mutable global in the package, and it is confined to lookup: it holds no services,
caches nothing, and cannot change behaviour except by returning a service configured elsewhere. A
resolver that throws yields null rather than propagating — a date helper falling back to stock
settings beats one that cannot construct.

---

[← Docs index](../../README.md#documentation)
