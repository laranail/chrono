<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Timezone\Collection;

use ArrayIterator;
use Countable;
use DateTimeInterface;
use Generator;
use IteratorAggregate;
use JsonSerializable;
use NoDiscard;
use Simtabi\Laranail\Chrono\Core\Enums\OffsetFormat;
use Simtabi\Laranail\Chrono\Core\Enums\SelectShape;
use Simtabi\Laranail\Chrono\Core\Enums\TimezoneField;
use Simtabi\Laranail\Chrono\Core\Timezone\Value\Timezone;
use Traversable;

/**
 * An immutable list of timezones, keyed by identifier.
 *
 * @implements IteratorAggregate<string, Timezone>
 */
final readonly class TimezoneCollection implements Countable, IteratorAggregate, JsonSerializable
{
    /** @var array<string, Timezone> */
    public array $items;

    /** @param iterable<Timezone> $timezones */
    public function __construct(iterable $timezones = [])
    {
        $keyed = [];

        foreach ($timezones as $timezone) {
            $keyed[$timezone->identifier] = $timezone;
        }

        $this->items = $keyed;
    }

    public static function empty(): self
    {
        return new self;
    }

    /** @return list<Timezone> */
    public function all(): array
    {
        return array_values($this->items);
    }

    /** @return list<string> */
    public function identifiers(): array
    {
        return array_keys($this->items);
    }

    public function get(string $identifier): ?Timezone
    {
        return $this->items[$identifier] ?? null;
    }

    public function has(string $identifier): bool
    {
        return isset($this->items[$identifier]);
    }

    public function first(): ?Timezone
    {
        return array_first($this->items);
    }

    public function last(): ?Timezone
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

    /** @param callable(Timezone): bool $predicate */
    #[NoDiscard]
    public function filter(callable $predicate): self
    {
        return new self(array_filter($this->items, $predicate));
    }

    /** @param callable(Timezone): bool $predicate */
    #[NoDiscard]
    public function reject(callable $predicate): self
    {
        return $this->filter(static fn (Timezone $t): bool => ! $predicate($t));
    }

    #[NoDiscard]
    public function sortBy(TimezoneField $field, bool $descending = false, ?DateTimeInterface $at = null): self
    {
        $items = $this->all();
        usort($items, $field->comparator($descending, $at));

        return new self($items);
    }

    #[NoDiscard]
    public function take(int $count): self
    {
        return new self(array_slice($this->all(), 0, $count));
    }

    #[NoDiscard]
    public function skip(int $count): self
    {
        return new self(array_slice($this->all(), $count));
    }

    /**
     * @template T
     *
     * @param  callable(Timezone): T  $callback
     * @return list<T>
     */
    public function map(callable $callback): array
    {
        return array_values(array_map($callback, $this->items));
    }

    /** @return array<string, self> */
    public function groupBy(TimezoneField $field, ?DateTimeInterface $at = null): array
    {
        $groups = [];

        foreach ($this->items as $timezone) {
            $groups[(string) $field->valueFor($timezone, $at)][] = $timezone;
        }

        ksort($groups);

        return array_map(static fn (array $group): self => new self($group), $groups);
    }

    /** @return list<string|int> */
    public function pluck(TimezoneField $field, ?DateTimeInterface $at = null): array
    {
        return $this->map(static fn (Timezone $t): string|int => $field->valueFor($t, $at));
    }

    /**
     * Render for a `<select>`.
     *
     * @return array<string, mixed>|list<array<string, mixed>>
     */
    #[NoDiscard]
    public function toSelectOptions(
        SelectShape $shape = SelectShape::Grouped,
        ?DateTimeInterface $at = null,
        bool $rtl = false,
    ): array {
        return match ($shape) {
            SelectShape::Flat => $this->flatOptions($at),
            SelectShape::Grouped => $this->groupedOptions($at, useIdentifier: false),
            SelectShape::Formed => $this->groupedOptions($at, useIdentifier: true),
            SelectShape::Payload => $this->payloadOptions($at, $rtl),
        };
    }

    /** @return Generator<string, Timezone> */
    public function lazy(): Generator
    {
        yield from $this->items;
    }

    /** @return list<array<string, mixed>> */
    public function toArray(?DateTimeInterface $at = null): array
    {
        return $this->map(static fn (Timezone $t): array => $t->toArray($at));
    }

    /** @return list<array<string, mixed>> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /** @return Traversable<string, Timezone> */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    /** @return array<string, string> */
    private function flatOptions(?DateTimeInterface $at): array
    {
        $options = [];

        foreach ($this->items as $identifier => $timezone) {
            $country = $timezone->countryCode();

            $options[$identifier] = sprintf(
                '%s%s (%s)',
                $timezone->city(),
                $country === null ? '' : ', '.$country,
                $timezone->offset($at)->format(OffsetFormat::Utc),
            );
        }

        return $options;
    }

    /** @return array<string, array<string, string>> */
    private function groupedOptions(?DateTimeInterface $at, bool $useIdentifier): array
    {
        $groups = [];

        foreach ($this->items as $identifier => $timezone) {
            $region = $timezone->region()->value ?? 'Other';

            $groups[$region][$identifier] = sprintf(
                '%s (%s)',
                $useIdentifier ? $identifier : $timezone->city(),
                $timezone->offset($at)->format(OffsetFormat::Utc),
            );
        }

        ksort($groups);

        return $groups;
    }

    /** @return list<array<string, mixed>> */
    private function payloadOptions(?DateTimeInterface $at, bool $rtl): array
    {
        return $this->map(static function (Timezone $timezone) use ($at, $rtl): array {
            $offset = $timezone->offset($at);
            $country = $timezone->countryCode();

            return [
                'id' => $timezone->identifier,
                'label' => sprintf('%s (%s)', $timezone->city(), $offset->format(OffsetFormat::Utc)),
                'city' => $timezone->city(),
                'region' => $timezone->region()?->value,
                'country' => $country,
                'offset' => $offset->seconds,
                'offset_label' => $offset->format(),
                'abbreviation' => $timezone->abbreviation($at),
                'dst' => $timezone->isDst($at),
                // Lowercased so a client-side filter can match without normalising first.
                'search' => strtolower(implode(' ', array_filter(
                    [$timezone->identifier, $timezone->city(), $country, $timezone->abbreviation($at), $offset->format()],
                    static fn (?string $part): bool => $part !== null && $part !== '',
                ))),
                'dir' => $rtl ? 'rtl' : 'ltr',
            ];
        });
    }
}
