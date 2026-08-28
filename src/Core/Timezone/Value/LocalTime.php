<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Timezone\Value;

use NoDiscard;
use JsonSerializable;
use DateTimeImmutable;
use Simtabi\Laranail\Chrono\Core\Enums\GapPolicy;
use Simtabi\Laranail\Chrono\Core\Enums\LocalTimeKind;
use Simtabi\Laranail\Chrono\Core\Enums\AmbiguityPolicy;
use Simtabi\Laranail\Chrono\Core\Exception\SkippedLocalTime;
use Simtabi\Laranail\Chrono\Core\Exception\AmbiguousLocalTime;

/**
 * The result of asking "what instant is this wall-clock reading, in this zone?" — including the
 * cases where the honest answer is "none" or "two".
 *
 * This is the type a booking form wants: inspect first, render a "which 1:30 did you mean?" prompt
 * when `isAmbiguous()`, and only then resolve. No exceptions on the happy path.
 */
final readonly class LocalTime implements JsonSerializable
{
    /** @param list<DateTimeImmutable> $candidates */
    public function __construct(
        public LocalTimeKind $kind,
        public string $localTime,
        public string $identifier,
        public array $candidates,
        public ?Transition $transition = null,
    ) {}

    public function isValid(): bool
    {
        return $this->kind === LocalTimeKind::Valid;
    }

    public function isGap(): bool
    {
        return $this->kind === LocalTimeKind::Gap;
    }

    public function isAmbiguous(): bool
    {
        return $this->kind === LocalTimeKind::Ambiguous;
    }

    /** The first matching instant, or null in a gap. */
    public function earlier(): ?DateTimeImmutable
    {
        return $this->candidates[0] ?? null;
    }

    /** The second matching instant when ambiguous; the only one when valid; null in a gap. */
    public function later(): ?DateTimeImmutable
    {
        return $this->candidates === [] ? null : $this->candidates[count($this->candidates) - 1];
    }

    /**
     * Collapse to a single instant by applying the policies.
     *
     * @throws SkippedLocalTime when the reading is in a gap and the policy is Throw
     * @throws AmbiguousLocalTime when the reading is ambiguous and the policy is Throw
     */
    #[NoDiscard]
    public function resolve(
        GapPolicy $gap = GapPolicy::Forward,
        AmbiguityPolicy $ambiguity = AmbiguityPolicy::Earlier,
    ): DateTimeImmutable {
        return match ($this->kind) {
            LocalTimeKind::Valid     => $this->candidates[0],
            LocalTimeKind::Ambiguous => match ($ambiguity) {
                AmbiguityPolicy::Earlier => $this->candidates[0],
                AmbiguityPolicy::Later   => $this->candidates[count($this->candidates) - 1],
                AmbiguityPolicy::Throw   => throw AmbiguousLocalTime::for($this->localTime, $this->identifier, $this->candidates),
            },
            LocalTimeKind::Gap => $this->resolveGap($gap),
        };
    }

    /** @return array{kind: string, local_time: string, identifier: string, candidates: list<string>} */
    public function toArray(): array
    {
        return [
            'kind'       => $this->kind->value,
            'local_time' => $this->localTime,
            'identifier' => $this->identifier,
            'candidates' => array_map(
                static fn (DateTimeImmutable $candidate): string => $candidate->format('c'),
                $this->candidates,
            ),
        ];
    }

    /** @return array{kind: string, local_time: string, identifier: string, candidates: list<string>} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private function resolveGap(GapPolicy $policy): DateTimeImmutable
    {
        if ($policy === GapPolicy::Throw || ! $this->transition instanceof Transition) {
            throw SkippedLocalTime::for($this->localTime, $this->identifier, $this->transition);
        }

        // The naive reading interpreted as if it were UTC; subtracting an offset gives an instant.
        $naive = new DateTimeImmutable($this->localTime . ' UTC')->getTimestamp();

        $offset = $policy === GapPolicy::Forward
            ? $this->transition->offsetBefore->seconds   // shift forward past the gap
            : $this->transition->offsetAfter->seconds;   // shift backward before it

        return new DateTimeImmutable('@' . ($naive - $offset));
    }
}
