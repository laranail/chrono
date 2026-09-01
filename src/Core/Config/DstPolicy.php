<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Config;

use NoDiscard;
use Simtabi\Laranail\Chrono\Core\Enums\AmbiguityPolicy;
use Simtabi\Laranail\Chrono\Core\Enums\GapPolicy;

/**
 * What this application does with the two wall-clock readings a year that are not instants.
 *
 * Every observing zone produces one reading that names no instant and one that names two. PHP
 * resolves both silently, and its choice for the ambiguous one is not consistent between zones:
 * `Europe/London 2025-10-26 01:30` yields the later instant while `America/New_York 2025-11-02
 * 01:30` yields the earlier, from the same build. An application that stores bookings in both cities
 * gets opposite disambiguation and nothing tells it so.
 *
 * Carrying the pair as one object is what makes the choice application-wide rather than per-call. A
 * caller may still override at the call site; the point is that not passing anything is a decision
 * the application made once, not a default it inherited from the engine.
 */
final readonly class DstPolicy
{
    public function __construct(
        public GapPolicy $gap = GapPolicy::Forward,
        public AmbiguityPolicy $ambiguity = AmbiguityPolicy::Earlier,
    ) {}

    /** @param array<string, mixed> $config */
    public static function fromArray(array $config): self
    {
        $gap = $config['on_gap'] ?? null;
        $ambiguity = $config['on_ambiguous'] ?? null;

        return new self(
            gap: $gap instanceof GapPolicy
                ? $gap
                : GapPolicy::tryFrom(is_string($gap) ? $gap : '') ?? GapPolicy::Forward,
            ambiguity: $ambiguity instanceof AmbiguityPolicy
                ? $ambiguity
                : AmbiguityPolicy::tryFrom(is_string($ambiguity) ? $ambiguity : '') ?? AmbiguityPolicy::Earlier,
        );
    }

    /** The pair that refuses to guess — what bookings, payroll and billing want. */
    public static function strict(): self
    {
        return new self(GapPolicy::Throw, AmbiguityPolicy::Throw);
    }

    /** The pair that reproduces PHP's own behaviour, so adopting this package changes nothing. */
    public static function permissive(): self
    {
        return new self;
    }

    #[NoDiscard]
    public function onGap(GapPolicy $policy): self
    {
        return clone ($this, ['gap' => $policy]);
    }

    #[NoDiscard]
    public function onAmbiguity(AmbiguityPolicy $policy): self
    {
        return clone ($this, ['ambiguity' => $policy]);
    }

    /** True when neither reading is resolved silently. */
    public function isStrict(): bool
    {
        return $this->gap === GapPolicy::Throw && $this->ambiguity === AmbiguityPolicy::Throw;
    }

    /** @return array{on_gap: string, on_ambiguous: string} */
    public function toArray(): array
    {
        return ['on_gap' => $this->gap->value, 'on_ambiguous' => $this->ambiguity->value];
    }
}
