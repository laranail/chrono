<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Concerns;

use NoDiscard;
use Simtabi\Laranail\Chrono\Core\Config\SelectOptions;
use Simtabi\Laranail\Chrono\Core\Enums\SelectShape;
use Simtabi\Laranail\Chrono\Core\Presentation\TimezonePresenter;
use Simtabi\Laranail\Chrono\Core\Support\ServiceResolver;

/**
 * Building a timezone list for a consumer — a form, a JSON endpoint, an export, a console table.
 *
 * The presenter it hands back is already carrying this application's catalogue restrictions and
 * display settings, which is the part that is easy to get wrong by hand: a controller that builds
 * its own list offers zones the validation rules will then reject, and nobody finds out until a user
 * picks one.
 *
 *     final class TimezoneController
 *     {
 *         use PresentsTimezones;
 *
 *         public function index(Request $request): array
 *         {
 *             return $this->presentZones()
 *                 ->locale($request->getPreferredLanguage())
 *                 ->forApi();
 *         }
 *     }
 *
 * Requires {@see InteractsWithTimezones}; the umbrella {@see InteractsWithChrono} pulls in both.
 */
trait PresentsTimezones
{
    use InteractsWithTimezones;

    private ?SelectOptions $chronoSelectOptions = null;

    #[NoDiscard]
    public function withSelectOptions(SelectOptions $options): static
    {
        $clone = clone $this;
        $clone->chronoSelectOptions = $options;

        return $clone;
    }

    protected function selectOptions(): SelectOptions
    {
        return $this->chronoSelectOptions ??= ServiceResolver::resolve(SelectOptions::class) ?? new SelectOptions;
    }

    /** A presenter over the zones this application offers. */
    #[NoDiscard]
    protected function presentZones(): TimezonePresenter
    {
        return new TimezonePresenter($this->zoneQuery());
    }

    /**
     * The picker array, in the application's configured shape unless one is named.
     *
     * @return array<array-key, mixed>
     */
    #[NoDiscard]
    protected function zoneOptions(?SelectShape $shape = null): array
    {
        return $this->presentZones()->toShape($shape ?? $this->selectOptions()->shape);
    }

    /**
     * Bare identifiers — for an `in:` rule, an export header, a console choice list.
     *
     * @return list<string>
     */
    #[NoDiscard]
    protected function zoneIdentifiers(): array
    {
        return $this->presentZones()->forIdentifiers();
    }
}
