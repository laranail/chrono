# Test with a frozen clock

Make daylight-saving assertions mean the same thing in five years.

```php
use Simtabi\Laranail\Chrono\Core\Testing\FrozenClock;
use Simtabi\Laranail\Chrono\Core\Timezone\Timezones;

$winter = (new Timezones)->withClock(new FrozenClock('2026-01-15T12:00:00Z'));
$summer = (new Timezones)->withClock(new FrozenClock('2026-07-15T12:00:00Z'));

$winter->of('America/New_York')->offset()->format();  // '-05:00'
$summer->of('America/New_York')->offset()->format();  // '-04:00'
```

The clock threads into every `Timezone` the service builds and every result a query materialises, so
one injection covers the whole surface.

## In a Laravel test

Use the shipped trait rather than binding by hand:

```php
use Simtabi\Laranail\Chrono\Testing\FreezesChronoTime;

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

Binding `ClockInterface` alone is not enough, and this is the part hand-rolled freezing misses: a
singleton resolved *before* the bind captured the old clock by constructor injection and keeps
reading the wall clock for the rest of the test. The trait rebinds and rebuilds.

`travelChronoTo()` moves the clock. `freezeAtNextTransition('Europe/London')` freezes at the moment a
daylight-saving change takes effect, asked of the database rather than hard-coded — the dates move
every year, and a hard-coded one silently stops testing anything.

The provider binds the clock with `bindIf`, never `singleton`, so an application or a test that has
already bound its own PSR-20 clock keeps it.

## In a class of your own

`InteractsWithClock` extends the same guarantee to code that merely uses the package:

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

See [Traits](../tools/concerns.md).

## Why it matters here

Without an injected clock, `offset()`, `isDst()` and `abbreviation()` would answer differently
depending on the day the suite ran — a test written in January passing until March. For a package
whose subject is daylight saving, that is not a minor inconvenience; it is the failure mode.

An architecture test enforces it: nothing in `src/` calls `time()` or `date()`, with a single
exemption for `SystemClock`.

## Pinning the process timezone too

`phpunit.xml` sets `date.timezone=UTC` so a developer's `php.ini` cannot change a result:

```xml
<php>
    <ini name="date.timezone" value="UTC"/>
</php>
```

## Fixtures that will and will not age

Assertions about statute and history — US and EU daylight saving, Samoa's 2011 dateline move — can
be hard-coded. Assertions about a government's current intent cannot: Morocco shifts for Ramadan,
and Iran, Chile, Egypt and Fiji have all changed their minds.

Tag the second kind and keep it out of the main suite:

```php
it('handles a zone with more than two changes in a year', function (): void {
    expect((new Timezone('Africa/Cairo'))->transitionsIn(2014)->count())->toBe(4);
})->group('tzdata');
```

```bash
composer test          # excludes the tzdata group
composer test-tzdata   # runs only it
```

A weekly workflow runs the tagged group and opens an issue on drift, so a CI image rebuilt
mid-sprint cannot turn anyone's pull request red.

---

[← Docs index](../../README.md#documentation)
