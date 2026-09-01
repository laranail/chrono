<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\Laranail\Chrono\Core\Timezone\Value\Timezone;
use Simtabi\Laranail\Chrono\Rules\Concerns\ResolvesZoneFromRequest;

/**
 * The wall-clock reading must be a time that actually happened.
 *
 * Once a year every daylight-saving zone skips an hour, and a reading inside it names no instant at
 * all. `2026-03-08 02:30` in New York is such a reading — and PHP resolves it to `03:30 EDT`
 * without a word. Laravel's `date` and `date_format` both accept it happily, because as a *string*
 * it is perfectly well-formed.
 *
 * For a booking or a shift roster that is a real defect: the user picks a time, is shown a
 * confirmation for a different one, and nothing ever flagged it.
 *
 *     'starts_at' => ['required', 'date_format:Y-m-d H:i', new DateTimeExists('timezone')],
 *
 * The zone comes from another field of the same request. With no argument it uses the configured
 * default.
 */
final class DateTimeExists implements DataAwareRule, ValidationRule
{
    use ResolvesZoneFromRequest;

    public function __construct(private readonly ?string $timezoneField = 'timezone') {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $zone = $this->zone($this->timezoneField);

        // No resolvable zone means there is nothing to check against. The timezone field has its
        // own rule; failing here too would report one mistake twice.
        if (! $zone instanceof Timezone) {
            return;
        }

        if ($zone->inspect($value)->isGap()) {
            $fail('laranail-chrono::validation.datetime_exists')->translate([
                'attribute' => $attribute,
                'timezone' => $zone->identifier,
            ]);
        }
    }
}
