<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Support;

use Closure;
use Throwable;

/**
 * How a framework-free trait finds a configured service when a framework happens to be present.
 *
 * The traits in `Core\Concerns` have to work in two worlds. Inside a Laravel application they must
 * return the *configured* services — the ones carrying this application's daylight-saving policy,
 * catalogue and display settings — or they would quietly disagree with everything else. In a plain
 * PHP script, a queue worker booted without the framework, or a unit test with no container, they
 * must still work, with sensible defaults and no wiring.
 *
 * A settable resolver is the seam. The service provider hands one over at boot; without it, every
 * lookup returns null and the trait constructs its own default. Nothing in `Core` learns what a
 * container is.
 *
 * This is deliberately the only mutable global in the package, and it is confined to lookup: it
 * holds no services, caches nothing, and cannot change how anything behaves except by returning a
 * service that was already configured elsewhere. Prefer injecting a service outright, or the
 * `with…()` method each trait provides, whenever the call site can.
 */
final class ServiceResolver
{
    /** @var (Closure(class-string): ?object)|null */
    private static ?Closure $resolver = null;

    /**
     * Install the resolver. Called once, from the service provider.
     *
     * @param  (Closure(class-string): ?object)|null  $resolver
     */
    public static function using(?Closure $resolver): void
    {
        self::$resolver = $resolver;
    }

    /** Forget the resolver — for a test that wants to prove the no-framework path. */
    public static function forget(): void
    {
        self::$resolver = null;
    }

    public static function isBound(): bool
    {
        return self::$resolver instanceof Closure;
    }

    /**
     * The configured instance of a service, or null when nothing can supply one.
     *
     * Never throws. A resolver that fails — a container that is mid-teardown, a binding that was
     * removed — yields null and the caller falls back to its own default, because a date helper
     * failing to construct is a worse outcome than one using stock settings.
     *
     * @template T of object
     *
     * @param  class-string<T>  $service
     * @return T|null
     */
    public static function resolve(string $service): ?object
    {
        if (! self::$resolver instanceof Closure) {
            return null;
        }

        try {
            $resolved = (self::$resolver)($service);
        } catch (Throwable) {
            return null;
        }

        return $resolved instanceof $service ? $resolved : null;
    }
}
