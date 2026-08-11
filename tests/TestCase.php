<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Simtabi\Laranail\Chrono\Core\Support\ServiceResolver;
use Simtabi\Laranail\Chrono\Providers\ChronoServiceProvider;
use Simtabi\Laranail\Chrono\Testing\FreezesChronoTime;

/**
 * Base case for tests that need a booted Laravel application.
 *
 * Unit tests under `tests/Unit` deliberately do **not** use this: `src/Core` is framework-free, and
 * testing it without a container is the cheapest possible proof that it stayed that way.
 *
 * Time is frozen through the package's own `FreezesChronoTime`, which is also what the docs tell
 * consumers to use. Freezing by hand here would mean the shipped helper was never exercised.
 */
abstract class TestCase extends OrchestraTestCase
{
    use FreezesChronoTime;

    /** The instant every test sees. Mid-June, so no northern DST transition is nearby. */
    public const string FROZEN_AT = '2026-06-15T12:00:00Z';

    protected function setUp(): void
    {
        parent::setUp();

        date_default_timezone_set('UTC');

        $this->freezeChronoTime(self::FROZEN_AT);
    }

    /**
     * Drop the container lookup the provider installed.
     *
     * `ServiceResolver` is a static, and this suite runs framework-free unit tests in the same
     * process as booted ones. Leaving it set would let a unit test resolve services out of a
     * torn-down Testbench application — and, worse, would let the no-container path pass while
     * never actually being taken.
     */
    protected function tearDown(): void
    {
        ServiceResolver::forget();

        parent::tearDown();
    }

    /** @return list<class-string> */
    protected function getPackageProviders($app): array
    {
        return [ChronoServiceProvider::class];
    }
}
