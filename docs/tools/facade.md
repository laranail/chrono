# Facades and helpers

Two facades and three helpers, all of which delegate to services you can inject directly.

## `Chrono` — the root

One import that reaches every module. Each accessor returns the module's own service, so
`Chrono::timezones()` and an injected `Timezones` are the same object with the same configuration.

```php
use Simtabi\Laranail\Chrono\Facades\Chrono;

Chrono::timezones();   // Core\Timezone\Timezones
Chrono::format();      // Core\Format\DateFormatter
Chrono::parse();       // Core\Format\DateParser
Chrono::humanize();    // Core\Humanize\Humanizer
Chrono::present();     // Core\Presentation\TimezonePresenter — pickers, APIs, form components
Chrono::convert();     // Core\Conversion\TimeConverter — "what time is that, over there?"

Chrono::zone('Asia/Calcutta');  // shorthand for timezones()->of()
Chrono::display();              // the offset shape, date format and locale everything defaults to
Chrono::version();              // PHP's tzdata release
```

`present()` and `convert()` are builders rather than long-lived services, so each call returns a
fresh one already carrying the application's [display settings](../configuration.md#display).

`calendar()`, `holidays()` and `recur()` arrive with their modules in `v0.2` and `v0.3`.

## `Timezones` — the timezone surface

Named in the plural so the generated `Core\Enums\Timezone` enum keeps its singular name. The two can
then be imported into the same file without an alias, and migrating off
`laranail/package-tools`' enum stays a one-line change.

```php
use Simtabi\Laranail\Chrono\Facades\Timezones;

// interpreting
Timezones::of($input);            // Timezone, or throws
Timezones::tryOf($input);         // ?Timezone
Timezones::resolve($input);       // canonical identifier
Timezones::explain($input);       // ?Resolution — which strategy, how confident, alternatives
Timezones::candidates($input);    // TimezoneCollection
Timezones::has($input);           // bool
Timezones::canonicalise($id);     // follows an alias

// fetching
Timezones::query();               // TimezoneQuery
Timezones::all();
Timezones::inCountry('KE');
Timezones::inRegion(Region::Africa);
Timezones::utc();
Timezones::fallback();
Timezones::system();              // reads the process default; never sets it
Timezones::aliases();

// time
Timezones::now();                 // from the injected clock
Timezones::now('Asia/Tokyo');
Timezones::convert($instant, 'Asia/Tokyo');

// metadata
Timezones::version();
Timezones::fingerprint();
```

### Typed input

`of()`, `tryOf()`, `resolve()` and the rest take whatever spells a zone: a string, a `DateTimeZone`,
a `DateTimeInterface`, an `IntlTimeZone`, a `Timezone`, a `Tz` constant, any string-backed enum case,
or any `Stringable`.

```php
Timezones::of(Tz::AMERICA_NEW_YORK);              // a plain string constant
Timezones::of(TimezoneEnum::AmericaNewYork);      // the generated enum
Timezones::of(TimezoneLegacy::AsiaCalcutta);      // canonicalised like any other alias
Timezones::of($request->user()->timezone);        // whatever the column holds
```

An enum is unwrapped to the string it spells and then judged like any other string — never trusted
because of its type. That distinction matters for `TimezoneAbbreviation::CST`, which spells something
that is not an identifier and names sixty-two zones: it still has to earn its answer from the
abbreviation strategy, which is off by default.

## Reconfiguring

The service is immutable, so a reconfigured copy never affects the container binding.

```php
$lenient = Timezones::lenient();                     // fall back instead of throwing
$biased  = Timezones::preferring('US', 'GB');        // break ties for abbreviations and cities
$abbrev  = Timezones::allowingAbbreviations();
$frozen  = Timezones::withClock(new FrozenClock('2026-06-15T12:00:00Z'));
$fixture = Timezones::withRepository($arrayRepository);

$strict  = Timezones::onGap(GapPolicy::Throw)->onAmbiguity(AmbiguityPolicy::Throw);
$asWritten = Timezones::preservingAliases();         // keep `Asia/Calcutta` as `Asia/Calcutta`
$narrowed  = Timezones::withCatalogue($options);     // a different set of offered zones
```

Every zone the reconfigured service hands out inherits its settings, so `$strict->of(...)->at(...)`
refuses to guess without the call site restating it.

## Helpers

`function_exists`-guarded, so they compose with an application's own.

```php
timezones();                       // the Timezones service
timezones('Asia/Calcutta');        // a resolved Timezone
tz_offset('Africa/Nairobi');       // 'UTC +03:00' — the configured display.offset_format
in_timezone($instant, 'Asia/Tokyo'); // the same instant, re-expressed
```

## Injecting instead

Every facade has a service behind it, and injection is preferred in application code:

```php
use Simtabi\Laranail\Chrono\Core\Timezone\Timezones;

public function __construct(private readonly Timezones $timezones) {}
```

---

[← Docs index](../../README.md#documentation)
