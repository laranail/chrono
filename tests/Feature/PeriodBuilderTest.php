<?php

declare(strict_types=1);

use Simtabi\Laranail\Chrono\Core\Exception\InvalidPeriod;
use Simtabi\Laranail\Chrono\Core\Period\Boundaries;
use Simtabi\Laranail\Chrono\Core\Period\Precision;
use Simtabi\Laranail\Chrono\Facades\Chrono;

it('builds a period from two dates', function (): void {
    $period = Chrono::period()->from('2026-01-01')->to('2026-01-31')->days()->build();

    expect($period->length())->toBe(31)
        ->and($period->precision)->toBe(Precision::Day);
});

it('builds a period from a start and a length', function (): void {
    $period = Chrono::period()->from('2026-01-01')->lasting(7)->days()->build();

    expect($period->length())->toBe(7)
        ->and($period->includedEnd()->format('Y-m-d'))->toBe('2026-01-07');
});

it('names each precision', function (Precision $precision, string $method): void {
    $period = Chrono::period()->from('2026-01-01')->lasting(2)->{$method}()->build();

    expect($period->precision)->toBe($precision);
})->with([
    [Precision::Year, 'years'],
    [Precision::Month, 'months'],
    [Precision::Day, 'days'],
    [Precision::Hour, 'hours'],
    [Precision::Minute, 'minutes'],
    [Precision::Second, 'seconds'],
]);

it('names each boundary choice', function (Boundaries $boundaries, string $method): void {
    $period = Chrono::period()->from('2026-01-01')->to('2026-01-10')->{$method}()->build();

    expect($period->boundaries)->toBe($boundaries);
})->with([
    [Boundaries::IncludeAll, 'includingAll'],
    [Boundaries::ExcludeStart, 'excludingStart'],
    [Boundaries::ExcludeEnd, 'excludingEnd'],
    [Boundaries::ExcludeAll, 'excludingAll'],
]);

it('says what is missing rather than guessing', function (): void {
    expect(fn () => Chrono::period()->to('2026-01-31')->build())
        ->toThrow(InvalidPeriod::class, 'needs a start')
        ->and(fn () => Chrono::period()->from('2026-01-01')->build())
        ->toThrow(InvalidPeriod::class, 'needs an end');
});

it('hands back a fresh builder each time', function (): void {
    // A shared builder would let one call site overwrite another's dates.
    $first = Chrono::period();
    $second = Chrono::period();

    expect($first)->not->toBe($second);
});

it('seeds a collection directly', function (): void {
    expect(Chrono::period()->from('2026-01-01')->lasting(3)->days()->collect())->toHaveCount(1);
});

it('reaches the collection and visualizer from the facade', function (): void {
    $a = Chrono::period()->from('2026-01-01')->to('2026-01-10')->days()->build();
    $b = Chrono::period()->from('2026-01-05')->to('2026-01-20')->days()->build();

    expect(Chrono::periods($a, $b)->union())->toHaveCount(1)
        ->and(Chrono::visualize(20)->visualize(['a' => $a, 'b' => $b]))->toContain('[');
});
