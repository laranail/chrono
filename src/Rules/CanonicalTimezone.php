<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\Laranail\Chrono\Core\Timezone\Timezones;

/**
 * The value must be a canonical IANA identifier — not a deprecated alias.
 *
 * Laravel's bare `timezone` rule already rejects aliases — it validates against
 * `DateTimeZone::ALL`, which excludes them. Two things this adds:
 *
 * It reports the canonical form in the failure message, so a user submitting `Asia/Calcutta` is
 * told to use `Asia/Kolkata` rather than just that their input was invalid.
 *
 * And it is the right rule when the input has been through this package's resolver, which accepts
 * aliases on purpose. `timezone:all_with_bc` would accept them silently; this accepts them, then
 * insists on the canonical spelling.
 */
final readonly class CanonicalTimezone implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail('laranail-chrono::validation.timezone')->translate(['attribute' => $attribute]);

            return;
        }

        /** @var Timezones $timezones */
        $timezones = app(Timezones::class);

        if ($timezones->tryOf($value) === null) {
            $fail('laranail-chrono::validation.timezone')->translate(['attribute' => $attribute]);

            return;
        }

        $canonical = $timezones->canonicalise($value);

        if ($canonical !== $value) {
            $fail('laranail-chrono::validation.timezone_canonical')->translate([
                'attribute' => $attribute,
                'canonical' => $canonical,
            ]);
        }
    }
}
