<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\Laranail\Chrono\Core\Enums\TimezoneAbbreviation as AbbreviationEnum;

/**
 * The value must be a timezone abbreviation PHP knows.
 *
 * Laravel has nothing for this. It supersedes the 130-entry hand-maintained whitelist that
 * `laranail/validation` carried: the list here is generated from `DateTimeZone::listAbbreviations()`
 * and currently runs to 144, so it cannot fall behind the database.
 *
 * Validating an abbreviation is not the same as accepting one as a timezone — 96 of the 144 map to
 * more than one zone, `CST` to 62. This confirms the string is a real abbreviation; it does not
 * make it an identifier.
 */
final readonly class TimezoneAbbreviation implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || AbbreviationEnum::tryFrom(strtoupper($value)) === null) {
            $fail('chrono::validation.timezone_abbr')->translate(['attribute' => $attribute]);
        }
    }
}
