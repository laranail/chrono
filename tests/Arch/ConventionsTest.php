<?php

declare(strict_types=1);

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Str;
use Simtabi\Laranail\Chrono\Core\Enums\DayOfWeek;
use Simtabi\Laranail\Chrono\Core\Enums\Month;
use Simtabi\Laranail\Chrono\Core\Enums\Tz;
use Simtabi\Laranail\Chrono\Core\Exception\ChronoExceptionBase;
use Simtabi\Laranail\Chrono\Core\Timezone\Support\SystemClock;

arch('the core never sees the framework')
    ->expect('Simtabi\Laranail\Chrono\Core')
    ->not->toUse([
        Facade::class,
        Str::class,
        Collection::class,
        Carbon::class,
        CarbonImmutable::class,
    ]);

arch('everything declares strict types')
    ->expect('Simtabi\Laranail\Chrono')
    ->toUseStrictTypes();

arch('nothing left a debug helper behind')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r'])
    ->not->toBeUsed();

arch('contracts are interfaces')
    ->expect('Simtabi\Laranail\Chrono\Core\Contracts')
    ->toBeInterfaces();

// Two rules rather than one, because the backing type is not arbitrary. Identifiers, policies and
// formats are string-backed so they read plainly in config and JSON. `DayOfWeek` and `Month` are
// int-backed because ISO 8601 defines them numerically and PHP's own format characters return
// numbers — a string-backed Month would mean quoting "6" everywhere.
//
// `Tz` sits among the enums deliberately — it is the constants view of the same catalogue — so it
// is named as an exception rather than moved somewhere less discoverable.
arch('identifier and policy enums are string-backed')
    ->expect('Simtabi\Laranail\Chrono\Core\Enums')
    ->toBeStringBackedEnums()
    ->ignoring([
        'Simtabi\Laranail\Chrono\Core\Enums\Concerns',
        Tz::class,
        DayOfWeek::class,
        Month::class,
    ]);

arch('calendar enums are int-backed')
    ->expect([DayOfWeek::class, Month::class])
    ->toBeIntBackedEnums();

arch('value objects are final and readonly')
    ->expect('Simtabi\Laranail\Chrono\Core\Timezone\Value')
    ->toBeFinal()
    ->toBeReadonly();

arch('temporal types are final and readonly')
    ->expect('Simtabi\Laranail\Chrono\Core\Temporal')
    ->toBeFinal()
    ->toBeReadonly();

arch('exceptions are final')
    ->expect('Simtabi\Laranail\Chrono\Core\Exception')
    ->toBeFinal()
    ->ignoring(ChronoExceptionBase::class);

/**
 * Nothing may ask the operating system what time it is except the one class whose job that is.
 * Every other "now" comes from an injected PSR-20 clock, which is what makes a daylight-saving
 * assertion mean the same thing in five years as it does today.
 */
arch('only the system clock reads the wall clock')
    ->expect(['time', 'date', 'mktime', 'strtotime'])
    ->not->toBeUsed()
    ->ignoring(SystemClock::class);
