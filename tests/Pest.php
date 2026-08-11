<?php

declare(strict_types=1);

use Simtabi\Laranail\Chrono\Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test case bindings
|--------------------------------------------------------------------------
|
| Only Feature tests boot Laravel. Unit tests exercise src/Core directly with
| no container, which is the cheapest proof that the core stayed framework-free
| — a stray Illuminate dependency there fails the test run, not just deptrac.
|
*/

uses(TestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeIanaIdentifier', function (): void {
    expect(DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC))
        ->toContain($this->value);
});

expect()->extend('toHaveOffset', function (string $formatted): void {
    expect($this->value->offset()->format())->toBe($formatted);
});

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function utc(string $expression = 'now'): DateTimeImmutable
{
    return new DateTimeImmutable($expression, new DateTimeZone('UTC'));
}

function zone(string $identifier): DateTimeZone
{
    return new DateTimeZone($identifier);
}
