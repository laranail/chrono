<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\Laranail\Chrono\Core\Timezone\Timezones;

/**
 * The value must be one of the zones this application actually offers.
 *
 * Laravel's `timezone` rule validates against all 419 identifiers, or a whole region or country.
 * It cannot express "one of my catalogue" — so a product supporting forty zones still accepts
 * `Antarctica/Troll`, and the picker and the validator disagree.
 *
 * With no arguments it uses the configured catalogue, so the two cannot drift.
 */
final readonly class AllowedTimezone implements ValidationRule
{
    /** @param list<string> $allowed */
    public function __construct(private array $allowed = []) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail('laranail-chrono::validation.timezone')->translate(['attribute' => $attribute]);

            return;
        }

        /** @var Timezones $timezones */
        $timezones = app(Timezones::class);

        $resolved = $timezones->tryOf($value);

        if ($resolved === null) {
            $fail('laranail-chrono::validation.timezone')->translate(['attribute' => $attribute]);

            return;
        }

        $allowed = $this->allowed !== [] ? $this->allowed : $timezones->query()->identifiers();

        if (! in_array($resolved->identifier, $allowed, true)) {
            $fail('laranail-chrono::validation.timezone_allowed')->translate(['attribute' => $attribute]);
        }
    }
}
