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

Chrono::zone('Asia/Calcutta');  // shorthand for timezones()->of()
Chrono::version();              // PHP's tzdata release
```

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

## Reconfiguring

The service is immutable, so a reconfigured copy never affects the container binding.

```php
$lenient = Timezones::lenient();                     // fall back instead of throwing
$biased  = Timezones::preferring('US', 'GB');        // break ties for abbreviations and cities
$abbrev  = Timezones::allowingAbbreviations();
$frozen  = Timezones::withClock(new FrozenClock('2026-06-15T12:00:00Z'));
$fixture = Timezones::withRepository($arrayRepository);
```

## Helpers

`function_exists`-guarded, so they compose with an application's own.

```php
timezones();                       // the Timezones service
timezones('Asia/Calcutta');        // a resolved Timezone
tz_offset('Africa/Nairobi');       // '+03:00'
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
