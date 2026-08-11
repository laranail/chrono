<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Psr\Clock\ClockInterface;
use Simtabi\Laranail\Chrono\Core\Testing\FrozenClock;
use Simtabi\Laranail\Chrono\Providers\ChronoServiceProvider;

/**
 * Base case for tests that need a booted Laravel application.
 *
 * Unit tests under `tests/Unit` deliberately do **not** use this: `src/Core` is framework-free, and
 * testing it without a container is the cheapest possible proof that it stayed that way.
 */
abstract class TestCase extends OrchestraTestCase
{
    /** The instant every test sees. Mid-June, so no northern DST transition is nearby. */
    public const string FROZEN_AT = '2026-06-15T12:00:00Z';

    protected function setUp(): void
    {
        parent::setUp();

        date_default_timezone_set('UTC');

        $this->app->instance(ClockInterface::class, new FrozenClock(self::FROZEN_AT));
    }

    /** @return list<class-string> */
    protected function getPackageProviders($app): array
    {
        return [ChronoServiceProvider::class];
    }
}
