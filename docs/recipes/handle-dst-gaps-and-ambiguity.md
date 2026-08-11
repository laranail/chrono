# Handle a DST gap or ambiguity

Decide what a booking form does with the two wall-clock readings a year that name no instant, or
two.

## Ask before converting

```php
use Simtabi\Laranail\Chrono\Core\Enums\GapPolicy;

$zone = Timezones::of($user->timezone);
$status = $zone->inspect($request->input('starts_at'));

if ($status->isAmbiguous()) {
    // Two real answers. Show both and let the user choose.
    return view('booking.disambiguate', ['candidates' => $status->candidates]);
}

if ($status->isGap()) {
    return back()->withErrors([
        'starts_at' => __('That time does not exist on that date — the clock skips it.'),
    ]);
}

$instant = $status->earlier();
```

Exceptions are the wrong tool here: the user has not made a mistake, they have hit a real ambiguity
in their own calendar.

## Fail loudly instead

For payroll, billing or anything a human is charged for, refuse rather than pick:

```php
'dst' => [
    'on_gap'       => 'throw',
    'on_ambiguous' => 'throw',
],
```

Then catch at the boundary:

```php
use Simtabi\Laranail\Chrono\Core\Exception\AmbiguousLocalTime;
use Simtabi\Laranail\Chrono\Core\Exception\SkippedLocalTime;

try {
    $instant = $zone->at($input);
} catch (SkippedLocalTime|AmbiguousLocalTime $e) {
    report($e);   // context() carries the zone, the reading and the candidates
    throw ValidationException::withMessages(['starts_at' => __('chrono::validation.dst')]);
}
```

## Per call, without changing configuration

```php
$zone->at($input, GapPolicy::Forward);                        // as PHP would
$zone->at($input, GapPolicy::Backward);                       // preserves duration when pairing
$zone->at($input, ambiguity: AmbiguityPolicy::Later);
```

## Why the default is not "correct"

`GapPolicy::Forward` and `AmbiguityPolicy::Earlier` reproduce PHP's own behaviour, so installing this
package changes nothing until you choose otherwise. They are the compatible defaults, not the right
answer — the right answer depends on what the time means in your domain.

The value the package adds by default is *consistency*: PHP's ambiguity choice varies by zone, and
these do not.

---

[← Docs index](../../README.md#documentation)
