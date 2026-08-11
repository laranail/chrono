<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Timezone\Resolver;

/**
 * A resolved identifier plus how confident we are and what else it could have been.
 *
 * Richer than a bare string because ambiguity is the normal case for two of the strategies: 96 of
 * the 144 timezone abbreviations map to more than one zone — `CST` alone matches 62 — so a caller
 * needs to be able to show alternatives rather than be handed a silent guess.
 */
final readonly class Resolution
{
    /** @param list<string> $alternatives */
    public function __construct(
        public string $identifier,
        public string $via,
        public float $confidence = 1.0,
        public array $alternatives = [],
    ) {}

    public function isCertain(): bool
    {
        return $this->confidence >= 1.0 && $this->alternatives === [];
    }

    public function isAmbiguous(): bool
    {
        return $this->alternatives !== [];
    }
}
