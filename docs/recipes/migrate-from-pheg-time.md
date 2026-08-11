# Migrate from `pheg`'s time toolbox

A method-by-method map off `simtabi/pheg`'s `Toolbox\Time`.

`pheg` is not modified and does not need to be uninstalled; it stays on PHP 8.2 with unconstrained
dependencies. Move call sites as you touch them.

## Picker shapes

The three shapes are reproduced byte for byte, and asserted by test.

| `pheg` | `chrono` |
|---|---|
| `Time::getTimezones()['flat']` | `Timezones::query()->toSelectOptions(SelectShape::Flat)` |
| `Time::getTimezones()['grouped']` | `…->toSelectOptions(SelectShape::Grouped)` |
| `Time::getTimezones()['formed']` | `…->toSelectOptions(SelectShape::Formed)` |
| — | `…->toSelectOptions(SelectShape::Payload)` — new: the array-of-objects a JS picker wants |

One behavioural difference worth knowing: `getTimezones()` listed every identifier including
deprecated aliases, so its output could contain the same zone twice. The query excludes them by
default. Pass `->includeDeprecated()` for the old set.

## Offsets

| `pheg` | `chrono` |
|---|---|
| `Time::formatDisplayOffset($s, true)` | `$zone->offset()->format(OffsetFormat::Utc)` — `UTC +03:00` |
| `Time::formatDisplayOffset($s, false)` | `$zone->offset()->format(OffsetFormat::Colon)` — `+03:00` |
| `Time::getTimezoneObject($tz)` | `Timezones::of($tz)->toDateTimeZone()` |

`of()` throws on unresolvable input where `getTimezoneObject()` fell back; use `tryOf()` for the old
behaviour.

## Conversion

| `pheg` | `chrono` |
|---|---|
| `Time::convertTime($dt, $fmt, $tz)` | `Chrono::format()->format(Timezones::convert($dt, $tz), $fmt)` |
| `Time::getCurrentTime(...)` | `Timezones::now($tz)` |
| `Time::getDateDifference($a, $b, ...)` | `Chrono::humanize()->duration($b->getTimestamp() - $a->getTimestamp())` |

## Server and client zones

`DateTime::configureTimezone()`, `getServerTimezone()` and `toClientTimezone()` held both zones in
`public static` properties. The concept was right; the mechanism is unsafe under Octane and
untestable in parallel.

The server zone is now configuration:

```php
config('laranail.chrono.default')
```

The viewer zone is whatever your application resolves per request — usually a column on the user —
and is passed explicitly:

```php
$zone = Timezones::of($user->timezone);
$zone->convert($order->created_at);
```

Per-request resolution with a driver chain arrives in `v0.2`.

## Humanised time

`Humanizer\DateTime\*` is replaced by `Chrono::humanize()`. The output differs deliberately: the old
classes were English-shaped, and the replacement uses ICU plural rules, so Arabic and Russian are
correct rather than mechanically pluralised.

Note `Time::getTimeAgo()` has an unconditional `return` on its first line with fifteen lines of dead
code after it — if you were calling it, you were not getting what the body suggests.

## Format catalogue

`pheg/config/supports.php` listed format strings with no engine behind them. `NamedFormat` replaces
it, and adds the distinction that catalogue lacked: machine formats never localise, human formats
resolve through ICU skeletons so each locale gets its own field order.

---

[← Docs index](../../README.md#documentation)
