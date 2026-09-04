<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Simtabi\Laranail\Chrono\Core\Timezone\Timezones;
use Simtabi\Laranail\Chrono\Core\Timezone\Support\OffsetParser;

/**
 * The value must be a UTC offset.
 *
 * Laravel validates identifiers; it has nothing for an offset. Accepts every spelling a client
 * might send — `+03:00`, `-0530`, `+3`, `GMT+3`, `UTC-5`, `Z`, or a bare count of seconds.
 *
 * `inUse()` additionally requires some real zone to be on that offset right now, which rejects
 * arithmetically valid but nonexistent values like `+13:37`.
 */
final readonly class TimezoneOffset implements ValidationRule
{
    public function __construct(private bool $mustBeInUse = false) {}

    /** Require that a real zone is currently on this offset. */
    public static function inUse(): self
    {
        return new self(mustBeInUse: true);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $seconds = match (true) {
            is_int($value)    => $value,
            is_string($value) => OffsetParser::tryParse($value),
            default           => null,
        };

        if ($seconds === null) {
            $fail('laranail-chrono::validation.timezone_offset')->translate(['attribute' => $attribute]);

            return;
        }

        if ($this->mustBeInUse && ! $this->anyZoneUses($seconds)) {
            $fail('laranail-chrono::validation.timezone_offset_in_use')->translate(['attribute' => $attribute]);
        }
    }

    private function anyZoneUses(int $seconds): bool
    {
        /** @var Timezones $timezones */
        $timezones = app(Timezones::class);

        return $timezones->query()->withOffset($seconds)->exists();
    }
}
