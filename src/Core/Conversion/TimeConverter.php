<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Conversion;

use DateTimeImmutable;
use DateTimeInterface;
use JsonException;
use NoDiscard;
use Simtabi\Laranail\Chrono\Core\Config\DisplayOptions;
use Simtabi\Laranail\Chrono\Core\Enums\AmbiguityPolicy;
use Simtabi\Laranail\Chrono\Core\Enums\GapPolicy;
use Simtabi\Laranail\Chrono\Core\Enums\OffsetFormat;
use Simtabi\Laranail\Chrono\Core\Timezone\Timezones;
use Simtabi\Laranail\Chrono\Core\Timezone\Value\Timezone;

/**
 * "What time is that, over there?" — for one instant or many, in one zone or many.
 *
 * The question sounds trivial and has three traps in it. The input may be a wall-clock reading
 * rather than an instant, in which case it needs a source zone before it means anything. That
 * reading may fall in a daylight-saving gap or overlap, so it may name no instant or two. And a
 * caller usually wants several zones at once — a meeting across three offices — which is a fan-out,
 * not three separate calls with three chances to pass a different instant.
 *
 * Everything before the terminal describes the question; the terminal decides the shape of the
 * answer.
 *
 *     Chrono::convert('2026-06-15 09:00')->from('Africa/Nairobi')->to('Europe/London')->first();
 *     Chrono::convert($instants)->to(['Europe/London', 'Asia/Tokyo'])->table();
 *
 * Both `convert()` and `to()` accept a single value or a list, so a caller never has to decide
 * between the scalar and the array form.
 */
final readonly class TimeConverter
{
    /**
     * @param list<string|DateTimeInterface> $inputs
     * @param list<string> $targets
     */
    public function __construct(
        private Timezones $timezones,
        private array $inputs = [],
        private array $targets = [],
        private ?string $source = null,
        private ?GapPolicy $gap = null,
        private ?AmbiguityPolicy $ambiguity = null,
        private ?string $format = null,
        private ?OffsetFormat $offsetFormat = null,
        private DisplayOptions $display = new DisplayOptions,
    ) {}

    // ── what to convert ─────────────────────────────────────────────────────────────────────

    /**
     * The instants or wall-clock readings to convert. One value or many.
     *
     * A `DateTimeInterface` is taken as an instant and its own zone is honoured. A string with no
     * offset is a wall-clock reading and needs `from()`; with an offset it is already an instant.
     *
     * @param string|DateTimeInterface|iterable<string|DateTimeInterface> $input
     */
    #[NoDiscard]
    public function of(string|DateTimeInterface|iterable $input): self
    {
        return clone ($this, ['inputs' => [...$this->inputs, ...$this->normalise($input)]]);
    }

    /** The zone a bare wall-clock reading should be read in. */
    #[NoDiscard]
    public function from(string|Timezone $zone): self
    {
        return clone ($this, ['source' => $zone instanceof Timezone ? $zone->identifier : $zone]);
    }

    /**
     * The zones to answer for. One or many; call it repeatedly and they accumulate.
     *
     * Takes whatever the resolver takes — a string, an enum case, a `DateTimeZone`, a `Timezone`,
     * anything `Stringable` — because a caller who has `$user->timezone` should not have to know
     * which of those it is. Each is resolved once per call, not once per input.
     *
     * @param mixed $zones one value, or any iterable of them
     */
    #[NoDiscard]
    public function to(mixed $zones): self
    {
        $identifiers = [];

        foreach (is_iterable($zones) ? $zones : [$zones] as $zone) {
            $identifiers[] = $zone instanceof Timezone
                ? $zone->identifier
                : $this->timezones->resolve($zone);
        }

        return clone ($this, ['targets' => array_values(array_unique([...$this->targets, ...$identifiers]))]);
    }

    /** Answer for every zone of a country — "what time is it across the US?" */
    #[NoDiscard]
    public function toCountry(string ...$countryCodes): self
    {
        $identifiers = [];

        foreach ($countryCodes as $code) {
            $identifiers = [...$identifiers, ...$this->timezones->inCountry($code)->identifiers()];
        }

        return $this->to($identifiers);
    }

    // ── how to interpret and render ─────────────────────────────────────────────────────────

    /** Override the application's configured gap policy for this conversion only. */
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

    #[NoDiscard]
    public function format(string $format): self
    {
        return clone ($this, ['format' => $format]);
    }

    #[NoDiscard]
    public function offsetFormat(OffsetFormat $format): self
    {
        return clone ($this, ['offsetFormat' => $format]);
    }

    // ── terminals ───────────────────────────────────────────────────────────────────────────

    /**
     * Every conversion, one per input per target.
     *
     * @return list<ConvertedTime>
     */
    #[NoDiscard]
    public function get(): array
    {
        $results = [];

        // Both hoisted deliberately. `resolvedTargets()` runs the full resolver chain per zone, so
        // leaving it in the inner loop would resolve the same ten zones once for every instant.
        $targets = $this->resolvedTargets();
        $format = $this->format ?? $this->display->dateTimeFormat;
        $offsetFormat = $this->offsetFormat ?? $this->display->offsetFormat;

        foreach ($this->instants() as $index => $instant) {
            foreach ($targets as $target) {
                $results[] = new ConvertedTime(
                    index: $index,
                    instant: $instant,
                    zone: $target,
                    local: $target->convert($instant),
                    format: $format,
                    offsetFormat: $offsetFormat,
                );
            }
        }

        return $results;
    }

    public function first(): ?ConvertedTime
    {
        return array_first($this->get());
    }

    /**
     * Keyed by identifier — the shape for a single instant across several zones.
     *
     * @return array<string, ConvertedTime>
     */
    #[NoDiscard]
    public function keyed(): array
    {
        $keyed = [];

        foreach ($this->get() as $converted) {
            $keyed[$converted->zone->identifier] = $converted;
        }

        return $keyed;
    }

    /**
     * A grid: one row per input, one column per zone. What a "meeting across offices" view wants.
     *
     * @return list<array<string, string>>
     */
    #[NoDiscard]
    public function table(): array
    {
        $rows = [];

        foreach ($this->get() as $converted) {
            $rows[$converted->index] ??= [];
            $rows[$converted->index][$converted->zone->identifier] = $converted->formatted();
        }

        return array_values($rows);
    }

    /** @return list<array<string, scalar|null>> */
    #[NoDiscard]
    public function forApi(): array
    {
        return array_map(static fn (ConvertedTime $c): array => $c->toArray(), $this->get());
    }

    /** @throws JsonException */
    #[NoDiscard]
    public function forJson(int $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES): string
    {
        return json_encode($this->forApi(), $flags | JSON_THROW_ON_ERROR);
    }

    /**
     * The instants the inputs denote, before any target zone is applied.
     *
     * @return list<DateTimeImmutable>
     */
    #[NoDiscard]
    public function instants(): array
    {
        $source = $this->source === null ? null : $this->timezones->of($this->source);

        return array_map(
            fn (string|DateTimeInterface $input): DateTimeImmutable => $this->toInstant($input, $source),
            $this->inputs,
        );
    }

    public function count(): int
    {
        return count($this->inputs) * max(1, count($this->targets));
    }

    // ── internals ───────────────────────────────────────────────────────────────────────────

    /**
     * @param string|DateTimeInterface|iterable<string|DateTimeInterface> $input
     * @return list<string|DateTimeInterface>
     */
    private function normalise(string|DateTimeInterface|iterable $input): array
    {
        if (! is_iterable($input)) {
            return [$input];
        }

        $values = [];

        foreach ($input as $value) {
            /** @var string|DateTimeInterface $value */
            $values[] = $value;
        }

        return $values;
    }

    /** @return list<Timezone> */
    private function resolvedTargets(): array
    {
        if ($this->targets === []) {
            return [$this->timezones->utc()];
        }

        return array_map($this->timezones->of(...), $this->targets);
    }

    private function toInstant(string|DateTimeInterface $input, ?Timezone $source): DateTimeImmutable
    {
        if ($input instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($input);
        }

        // A bare wall-clock reading goes through the source zone's daylight-saving policies rather
        // than PHP's silent resolution; with no source it is read as UTC. Passing null lets the
        // zone apply the application's configured pair.
        $zone = $source ?? $this->timezones->utc();

        return $zone->at($input, $this->gap, $this->ambiguity);
    }
}
