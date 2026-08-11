<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Timezone\Resolver;

use IntlTimeZone;
use Simtabi\Laranail\Chrono\Core\Contracts\TimezoneResolver;

/**
 * A Windows timezone name such as `Pacific Standard Time` or `E. Africa Standard Time`.
 *
 * Worth having because these arrive from .NET clients, Outlook invitations and Active Directory,
 * and nothing in PHP maps them without ICU. `getIDForWindowsID()` needs ICU >= 52 and is guarded
 * accordingly.
 */
final readonly class WindowsResolver implements TimezoneResolver
{
    public function key(): string
    {
        return 'windows';
    }

    public function resolve(mixed $input, ResolutionContext $context): ?Resolution
    {
        if (! is_string($input) || ! str_contains($input, ' ')) {
            return null;
        }

        if (! class_exists(IntlTimeZone::class) || ! method_exists(IntlTimeZone::class, 'getIDForWindowsID')) {
            return null;
        }

        $region = $context->preferredCountries[0] ?? null;
        $identifier = @IntlTimeZone::getIDForWindowsID($input, $region);

        if ($identifier === false && $region !== null) {
            $identifier = @IntlTimeZone::getIDForWindowsID($input);
        }

        return is_string($identifier) && $identifier !== ''
            ? new Resolution($identifier, $this->key(), 0.95)
            : null;
    }
}
