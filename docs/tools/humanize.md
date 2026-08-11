# Humanised time

Turning a span into a phrase, correctly, in any locale.

```php
Chrono::humanize()->diffForHumans($comment->created_at);              // '3 hours ago'
Chrono::humanize()->diffForHumans($event->starts_at);                 // 'in 2 days'
Chrono::humanize()->duration(93784);                                  // '1 day'
Chrono::humanize()->duration(93784, parts: 2);                        // '1 day 2 hours'
```

## Why this is a module and not a wrapper

PHP does not expose ICU's `RelativeDateTimeFormatter`. `IntlDateFormatter::RELATIVE_FULL` exists but
only reaches day granularity — it will say "tomorrow at 12:00 AM" and has no way to say "3 hours
ago" — so the phrasing has to be assembled here.

## Plural rules

Every number goes through `MessageFormatter`, which applies CLDR plural rules. Arabic has six
categories:

```php
$humanizer->duration(86400, 'ar');      // يوم واحد
$humanizer->duration(2 * 86400, 'ar');  // يومان
$humanizer->duration(5 * 86400, 'ar');  // ٥ أيام
```

Three distinct forms where a `singular|plural` pair has two. Laravel's `trans_choice` pipe syntax
cannot express them, which is why the catalogue holds ICU patterns rather than translation strings.

## Locales

English, Swahili, Arabic and French ship built in. Anything else falls back to English until an
application registers patterns.

```php
use Simtabi\Laranail\Chrono\Core\Humanize\MessageCatalogue;

$catalogue = (new MessageCatalogue)->with('pl', [
    'day'  => '{n, plural, one {# dzień} few {# dni} many {# dni} other {# dnia}}',
    'past' => '{value} temu',
    'now'  => 'przed chwilą',
]);

$humanizer = Chrono::humanize()->withCatalogue($catalogue);
```

Lookup walks `sw_KE` → `sw` → `en`, and the pattern is formatted **under the locale that owns it**.
That detail matters: ICU picks a plural branch from the locale tag it is handed, so rendering an
English pattern under an unknown tag falls through to `other` and produces "1 days".

Keys are the seven unit names — `second` through `year` — plus `past`, `future`, `now` and
`separator`.

## Elapsed, not wall-clock

Differences are measured from timestamps, never `DateTime::diff()`.

```php
$start = new DateTimeImmutable('2026-03-08 00:00', $newYork);
$end   = new DateTimeImmutable('2026-03-09 00:00', $newYork);

$start->diff($end)->d;                    // 1 — "one day"
$humanizer->duration(
    $end->getTimestamp() - $start->getTimestamp()
);                                        // '23 hours'
```

Both are true and they answer different questions; a duration wants the elapsed one.

## Granularity

Units promote at the conventional thresholds — 45 seconds becomes "a minute", 45 minutes becomes "an
hour". Months and years use average lengths, because "3 months ago" is a description rather than an
arithmetic claim.

```php
$humanizer->unitFor(44);          // TimeUnit::Second
$humanizer->unitFor(45);          // TimeUnit::Minute
$humanizer->unitFor(40 * 86400);  // TimeUnit::Month
```

## Determinism

`diffForHumans()` reads "now" from the injected clock, so a test asserting "3 hours ago" keeps
meaning that.

```php
$humanizer = Chrono::humanize()->withClock(new FrozenClock('2026-06-15T12:00:00Z'));
```

---

[← Docs index](../../README.md#documentation)
