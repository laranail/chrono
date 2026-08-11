# Build a timezone picker

Produce grouped, offset-labelled options that never list the same zone twice.

```php
use Simtabi\Laranail\Chrono\Core\Enums\SelectShape;
use Simtabi\Laranail\Chrono\Facades\Timezones;

$options = Timezones::query()
    ->orderByOffset()
    ->toSelectOptions(SelectShape::Grouped);
```

```blade
<select name="timezone">
    @foreach ($options as $region => $zones)
        <optgroup label="{{ $region }}">
            @foreach ($zones as $identifier => $label)
                <option value="{{ $identifier }}" @selected($identifier === $user->timezone)>
                    {{ $label }}
                </option>
            @endforeach
        </optgroup>
    @endforeach
</select>
```

Deprecated aliases are excluded by default, so `Asia/Calcutta` and `Asia/Kolkata` cannot both appear
— a bug that shipped in this estate's own hand-maintained list.

## A shorter list

Most products support a few dozen zones, not 419.

```php
$options = Timezones::query()
    ->inCountry('KE', 'TZ', 'UG', 'GB', 'US')
    ->orderByOffset()
    ->toSelectOptions(SelectShape::Grouped);
```

Or pin it in `config/chrono.php` so validation and the picker agree:

```php
'catalogue' => [
    'only' => ['UTC', 'Africa/Nairobi', 'Europe/London', 'America/New_York'],
],
```

## For a JavaScript component

`Payload` gives an array of objects with a pre-lowercased search token and a text direction.

```php
return Timezones::query()
    ->orderByOffset()
    ->toSelectOptions(SelectShape::Payload, rtl: locale_is_right_to_left(app()->getLocale()));
```

```json
{ "id": "Africa/Nairobi", "label": "Nairobi (UTC +03:00)", "city": "Nairobi", "country": "KE",
  "offset": 10800, "abbreviation": "EAT", "dst": false,
  "search": "africa/nairobi nairobi ke eat +03:00", "dir": "ltr" }
```

Filter on `search` client-side; it already contains the identifier, city, country, abbreviation and
offset, lowercased.

---

[← Docs index](../../README.md#documentation)
