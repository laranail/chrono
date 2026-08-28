<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Timezone\Collection;

use Countable;
use Generator;
use NoDiscard;
use Traversable;
use ArrayIterator;
use JsonSerializable;
use DateTimeInterface;
use IteratorAggregate;
use Simtabi\Laranail\Chrono\Core\Timezone\Value\Transition;

/**
 * An immutable, chronologically ordered list of transitions.
 *
 * @implements IteratorAggregate<int, Transition>
 */
final readonly class TransitionCollection implements Countable, IteratorAggregate, JsonSerializable
{
    /** @var list<Transition> */
    public array $items;

    /** @param list<Transition> $transitions */
    public function __construct(array $transitions = [])
    {
        usort($transitions, static fn (Transition $a, Transition $b): int => $a->timestamp <=> $b->timestamp);

        $this->items = $transitions;
    }

    public static function empty(): self
    {
        return new self;
    }

    /** @return list<Transition> */
    public function all(): array
    {
        return $this->items;
    }

    public function first(): ?Transition
    {
        return array_first($this->items);
    }

    public function last(): ?Transition
    {
        return array_last($this->items);
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    public function isNotEmpty(): bool
    {
        return $this->items !== [];
    }

    public function count(): int
    {
        return count($this->items);
    }

    /** @param callable(Transition): bool $predicate */
    #[NoDiscard]
    public function filter(callable $predicate): self
    {
        return new self(array_values(array_filter($this->items, $predicate)));
    }

    /** Changes where the clock jumped forward, skipping a range of local times. */
    #[NoDiscard]
    public function gaps(): self
    {
        return $this->filter(static fn (Transition $t): bool => $t->isGap());
    }

    /** Changes where the clock fell back, repeating a range of local times. */
    #[NoDiscard]
    public function overlaps(): self
    {
        return $this->filter(static fn (Transition $t): bool => $t->isOverlap());
    }

    #[NoDiscard]
    public function inYear(int $year): self
    {
        return $this->filter(
            static fn (Transition $t): bool => (int) $t->at->format('Y') === $year,
        );
    }

    #[NoDiscard]
    public function after(DateTimeInterface $instant): self
    {
        return $this->filter(
            static fn (Transition $t): bool => $t->timestamp > $instant->getTimestamp(),
        );
    }

    #[NoDiscard]
    public function before(DateTimeInterface $instant): self
    {
        return $this->filter(
            static fn (Transition $t): bool => $t->timestamp < $instant->getTimestamp(),
        );
    }

    /**
     * @template T
     *
     * @param callable(Transition): T $callback
     *
     * @return list<T>
     */
    public function map(callable $callback): array
    {
        return array_map($callback, $this->items);
    }

    /** @return Generator<int, Transition> */
    public function lazy(): Generator
    {
        yield from $this->items;
    }

    /** @return list<array<string, mixed>> */
    public function toArray(): array
    {
        return $this->map(static fn (Transition $t): array => $t->toArray());
    }

    /** @return list<array<string, mixed>> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /** @return Traversable<int, Transition> */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }
}
