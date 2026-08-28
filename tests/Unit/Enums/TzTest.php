<?php

declare(strict_types=1);

use Simtabi\Laranail\Chrono\Core\Enums\Tz;
use Simtabi\Laranail\Chrono\Core\Enums\Timezone;

it('has a constant for every canonical identifier, and no others', function (): void {
    $identifiers = Tz::identifiers();
    $live = DateTimeZone::listIdentifiers(DateTimeZone::ALL);

    expect(array_diff($identifiers, $live))->toBe([])
        ->and(array_diff($live, $identifiers))->toBe([])
        ->and(Tz::count())->toBe(count($live));
});

it('is a plain string, which is the point', function (): void {
    expect(Tz::AFRICA_NAIROBI)->toBeString()->toBe('Africa/Nairobi');
});

it('crosses to the enum and back', function (): void {
    expect(Tz::enum(Tz::ASIA_TOKYO))->toBe(Timezone::AsiaTokyo)
        ->and(Tz::nameOf('America/New_York'))->toBe('AMERICA_NEW_YORK')
        ->and(Tz::has('Africa/Nairobi'))->toBeTrue()
        ->and(Tz::has('Not/AZone'))->toBeFalse();
});

it('derives a label rather than storing one', function (): void {
    expect(Tz::label('America/Argentina/Salta'))->toBe('Salta')
        ->and(Tz::label('Africa/Nairobi'))->toBe('Nairobi')
        ->and(Tz::options())->toHaveKey('Africa/Nairobi');
});
