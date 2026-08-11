<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Rules;

use Closure;
use DateTimeImmutable;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\Laranail\Chrono\Core\Timezone\Value\Timezone;
use Simtabi\Laranail\Chrono\Rules\Concerns\ResolvesZoneFromRequest;

/**
 * The wall-clock reading must name exactly one instant.
 *
 * Once a year every daylight-saving zone repeats an hour, and a reading inside it names two.
 * `2026-11-01 01:30` in New York happened twice, an hour apart. PHP silently picks one — and *which*
 * one it picks differs between zones: `Europe/London` yields the later instant while
 * `America/New_York` yields the earlier, from the same build.
 *
 * Use this where the difference is billable and the user should be asked, rather than guessed at.
 * The message names both instants so the form can offer them.
 *
 *     'starts_at' => ['required', new DateTimeUnambiguous('timezone')],
 */
final class DateTimeUnambiguous implements DataAwareRule, ValidationRule
{
    use ResolvesZoneFromRequest;

    public function __construct(private readonly ?string $timezoneField = 'timezone') {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $zone = $this->zone($this->timezoneField);

        if (! $zone instanceof Timezone) {
            return;
        }

        $status = $zone->inspect($value);

        if (! $status->isAmbiguous()) {
            return;
        }

        $fail('chrono::validation.datetime_unambiguous')->translate([
            'attribute' => $attribute,
            'timezone' => $zone->identifier,
            'first' => $this->describe($status->earlier()),
            'second' => $this->describe($status->later()),
        ]);
    }

    private function describe(?DateTimeImmutable $instant): string
    {
        return $instant instanceof DateTimeImmutable ? $instant->format('H:i T') : '';
    }
}
