<?php

declare(strict_types=1);

use Simtabi\Laranail\Chrono\Core\Enums\OffsetFormat;
use Simtabi\Laranail\Chrono\Core\Timezone\Timezones;
use Simtabi\Laranail\Chrono\Core\Config\DisplayOptions;
use Simtabi\Laranail\Chrono\Core\Timezone\Value\Timezone;

if (! function_exists('timezones')) {
    /**
     * The timezone service, or a resolved zone when given input.
     */
    function timezones(mixed $input = null): Timezones|Timezone
    {
        /** @var Timezones $service */
        $service = app(Timezones::class);

        return $input === null ? $service : $service->of($input);
    }
}

if (! function_exists('tz_offset')) {
    /**
     * A formatted UTC offset for a zone, at an instant.
     *
     * Rendered in the shape `display.offset_format` names, so this agrees with the picker, the API
     * payload and the converted time. It previously took `Offset::format()`'s own default and
     * printed `+03:00` where the rest of the same application printed `UTC +03:00` — one zone,
     * two spellings, no setting that explained it.
     */
    function tz_offset(mixed $zone, ?DateTimeInterface $at = null, ?OffsetFormat $format = null): string
    {
        /** @var Timezones $service */
        $service = app(Timezones::class);

        /** @var DisplayOptions $display */
        $display = app(DisplayOptions::class);

        return $service->of($zone)->offset($at)->format($format ?? $display->offsetFormat);
    }
}

if (! function_exists('in_timezone')) {
    /**
     * Re-express an instant in another zone. The instant does not change, only its presentation.
     */
    function in_timezone(DateTimeInterface $instant, mixed $zone): DateTimeImmutable
    {
        /** @var Timezones $service */
        $service = app(Timezones::class);

        return $service->convert($instant, $zone);
    }
}
