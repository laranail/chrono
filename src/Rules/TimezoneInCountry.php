<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;
use Simtabi\Laranail\Chrono\Core\Timezone\Timezones;

/**
 * The zone must belong to the country named in another field of the same request.
 *
 * Laravel's `timezone:per_country,US` fixes the country when the rule is written, so a signup form
 * where the user picks both country and timezone cannot use it. This reads the country from the
 * request, which is the case that actually occurs.
 *
 *     'timezone' => ['required', new TimezoneInCountry('country')],
 */
final class TimezoneInCountry implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function __construct(private readonly string $countryField = 'country') {}

    /** @param array<string, mixed> $data */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail('chrono::validation.timezone')->translate(['attribute' => $attribute]);

            return;
        }

        $country = Arr::get($this->data, $this->countryField);

        // The country field carries its own rules; reporting its absence here too would surface one
        // mistake as two.
        if (! is_string($country) || $country === '') {
            return;
        }

        /** @var Timezones $timezones */
        $timezones = app(Timezones::class);
        $zone = $timezones->tryOf($value);

        if ($zone === null) {
            $fail('chrono::validation.timezone')->translate(['attribute' => $attribute]);

            return;
        }

        $allowed = $timezones->inCountry($country)->identifiers();

        if (! in_array($zone->identifier, $allowed, true)) {
            $fail('chrono::validation.timezone_country')->translate([
                'attribute' => $attribute,
                'country' => strtoupper($country),
            ]);
        }
    }
}
