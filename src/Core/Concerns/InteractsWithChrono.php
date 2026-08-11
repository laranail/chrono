<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Concerns;

/**
 * Everything, in one `use` line.
 *
 *     final class ShiftRoster
 *     {
 *         use InteractsWithChrono;
 *
 *         public function summarise(Shift $shift, string $viewerZone): string
 *         {
 *             $starts = $this->atLocal($shift->local_start, $shift->timezone);
 *
 *             return $this->formatDate($this->inZone($starts, $viewerZone))
 *                 . ' (' . $this->humanizeDate($starts) . ')';
 *         }
 *     }
 *
 * Composes {@see InteractsWithClock}, {@see InteractsWithTimezones}, {@see ResolvesLocalTimes},
 * {@see ConvertsTimezones}, {@see PresentsTimezones} and {@see RendersDateTimes}. Prefer a single
 * one of those when a class only needs one thing — the narrower trait states the dependency, and a
 * class that pulls in six services to format one date is telling the next reader something false.
 *
 * ## What "any setup" means
 *
 * Inside a Laravel application every accessor returns the *configured* service, so a class using
 * this trait obeys the same daylight-saving policy, catalogue and display settings as the rest of
 * the application. Outside one — a plain script, a console tool, a queue worker booted without the
 * framework, a unit test with no container — the same accessors construct working defaults. No
 * branch in your code, and nothing to register.
 *
 * ## Two constraints worth knowing
 *
 * The traits hold their resolved services in private properties, so they cannot be used by a
 * `readonly` class. Inject the services instead; that is what the classes in this package do.
 *
 * The `with…()` methods clone, so they are safe on a shared instance, and each is `#[\NoDiscard]`
 * because `$service->withClock($frozen);` as a statement is a silent no-op. The `set…()` variants
 * mutate, for a class assembled once at construction.
 */
trait InteractsWithChrono
{
    use ConvertsTimezones;
    use InteractsWithClock;
    use InteractsWithTimezones;
    use PresentsTimezones;
    use RendersDateTimes;
    use ResolvesLocalTimes;
}
