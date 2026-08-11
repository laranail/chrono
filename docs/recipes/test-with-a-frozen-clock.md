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

Bind it once and every resolved service picks it up:

```php
use Psr\Clock\ClockInterface;

protected function setUp(): void
{
    parent::setUp();

    $this->app->instance(ClockInterface::class, new FrozenClock('2026-06-15T12:00:00Z'));
}
```

The provider binds the clock with `bindIf`, never `singleton`, so an application or a test that has
already bound its own PSR-20 clock keeps it.

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
