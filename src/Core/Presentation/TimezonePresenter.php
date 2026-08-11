<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Presentation;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use JsonException;
use Locale;
use NoDiscard;
use Simtabi\Laranail\Chrono\Core\Enums\GroupBy;
use Simtabi\Laranail\Chrono\Core\Enums\OffsetFormat;
use Simtabi\Laranail\Chrono\Core\Enums\PresentationPreset;
use Simtabi\Laranail\Chrono\Core\Enums\TimezoneField;
use Simtabi\Laranail\Chrono\Core\Enums\ZoneField;
use Simtabi\Laranail\Chrono\Core\Timezone\Query\TimezoneQuery;
use Simtabi\Laranail\Chrono\Core\Timezone\Value\Timezone;

/**
 * Turns a timezone query into something a human or a client can consume.
 *
 * The problem it solves is that "the timezone list" is not one thing. A `<select>` wants two fields
 * grouped into `<optgroup>`s; a JSON API wants a flat list of a dozen fields a client can filter on;
 * a validation rule wants bare identifiers. Building all three from one array either ships eight
 * times the bytes the select needs, or forces the API client to make a second request.
 *
 * So the shape is chosen last. Everything up to the terminal call describes *what* to show, and the
 * terminal decides *how*:
 *
 *     $presenter->groupByContinent()->withOffset()->forSelect();
 *     $presenter->preset(PresentationPreset::Api)->forApi();
 *     $presenter->only(ZoneField::Id)->forIdentifiers();
 *
 * Immutable throughout: every builder method returns a clone, and every one is `#[\NoDiscard]`
 * because `$presenter->withFlag();` as a statement is a silent no-op.
 */
final readonly class TimezonePresenter
{
    /** @param list<ZoneField> $fields */
    public function __construct(
        private TimezoneQuery $query,
        private array $fields = [ZoneField::Id, ZoneField::Label],
        private GroupBy $groupBy = GroupBy::None,
        private string $labelTemplate = '{city} ({gmt})',
        private OffsetFormat $offsetFormat = OffsetFormat::Utc,
        private string $timeFormat = 'H:i',
        private ?string $locale = null,
        private bool $rtl = false,
        private ?DateTimeInterface $at = null,
        private string $catchAllGroup = 'Other',
    ) {}

    // ── what to show ────────────────────────────────────────────────────────────────────────

    /** Replace the field set entirely. */
    #[NoDiscard]
    public function only(ZoneField ...$fields): self
    {
        return clone ($this, ['fields' => array_values($fields)]);
    }

    /** Start from a named field set. */
    #[NoDiscard]
    public function preset(PresentationPreset $preset): self
    {
        return clone ($this, ['fields' => $preset->fields()]);
    }

    #[NoDiscard]
    public function with(ZoneField ...$fields): self
    {
        return clone ($this, ['fields' => array_values(array_unique([...$this->fields, ...$fields], SORT_REGULAR))]);
    }

    #[NoDiscard]
    public function without(ZoneField ...$fields): self
    {
        return clone ($this, [
            'fields' => array_values(array_filter(
                $this->fields,
                static fn (ZoneField $field): bool => ! in_array($field, $fields, true),
            )),
        ]);
    }

    /** Shorthands for the fields people reach for by name. */
    #[NoDiscard]
    public function withOffset(): self
    {
        return $this->with(ZoneField::Offset, ZoneField::OffsetLabel);
    }

    #[NoDiscard]
    public function withCurrentTime(): self
    {
        return $this->with(ZoneField::LocalTime);
    }

    #[NoDiscard]
    public function withFlag(): self
    {
        return $this->with(ZoneField::Flag);
    }

    #[NoDiscard]
    public function withCoordinates(): self
    {
        return $this->with(ZoneField::Latitude, ZoneField::Longitude);
    }

    // ── how to group ────────────────────────────────────────────────────────────────────────

    #[NoDiscard]
    public function groupBy(GroupBy $groupBy): self
    {
        return clone ($this, ['groupBy' => $groupBy]);
    }

    #[NoDiscard]
    public function groupByContinent(): self
    {
        return $this->groupBy(GroupBy::Continent);
    }

    #[NoDiscard]
    public function groupByCountry(): self
    {
        return $this->groupBy(GroupBy::Country);
    }

    #[NoDiscard]
    public function groupByOffset(): self
    {
        return $this->groupBy(GroupBy::Offset);
    }

    #[NoDiscard]
    public function flat(): self
    {
        return $this->groupBy(GroupBy::None);
    }

    // ── how to render ───────────────────────────────────────────────────────────────────────

    /**
     * The label template. Placeholders: `{id} {city} {country} {country_name} {continent} {gmt}
     * {offset} {abbr} {time} {flag}`.
     */
    #[NoDiscard]
    public function label(string $template): self
    {
        return clone ($this, ['labelTemplate' => $template]);
    }

    /** How `{gmt}` and the offset fields render — `UTC +03:00`, `+03:00`, `GMT+03:00`, `+3`, … */
    #[NoDiscard]
    public function offsetFormat(OffsetFormat $format): self
    {
        return clone ($this, ['offsetFormat' => $format]);
    }

    #[NoDiscard]
    public function timeFormat(string $format): self
    {
        return clone ($this, ['timeFormat' => $format]);
    }

    #[NoDiscard]
    public function locale(string $locale, ?bool $rtl = null): self
    {
        return clone ($this, [
            'locale' => $locale,
            'rtl' => $rtl ?? $this->isRightToLeft($locale),
        ]);
    }

    /** Evaluate offsets, DST state and local times at this instant rather than now. */
    #[NoDiscard]
    public function asOf(DateTimeInterface $instant): self
    {
        return clone ($this, ['at' => $instant]);
    }

    #[NoDiscard]
    public function catchAllGroup(string $label): self
    {
        return clone ($this, ['catchAllGroup' => $label]);
    }

    /** Reach through to the underlying query without leaving the chain. */
    #[NoDiscard]
    public function query(callable $callback): self
    {
        /** @var TimezoneQuery $query */
        $query = $callback($this->query);

        return clone ($this, ['query' => $query]);
    }

    // ── terminals: the shape is chosen last ─────────────────────────────────────────────────

    /**
     * `<select>` options. Grouped, this is `group => [id => label]`; flat, it is `id => label`.
     *
     * @return array<string, string>|array<string, array<string, string>>
     */
    #[NoDiscard]
    public function forSelect(): array
    {
        if ($this->groupBy === GroupBy::None) {
            $options = [];

            foreach ($this->zones() as $zone) {
                $options[$zone->identifier] = $this->renderLabel($zone);
            }

            return $options;
        }

        $groups = [];

        foreach ($this->zones() as $zone) {
            $groups[$this->groupFor($zone)][$zone->identifier] = $this->renderLabel($zone);
        }

        ksort($groups);

        return $groups;
    }

    /**
     * A list of arrays, one per zone, carrying the chosen fields.
     *
     * @return list<array<string, scalar|null>>|array<string, list<array<string, scalar|null>>>
     */
    #[NoDiscard]
    public function forApi(): array
    {
        if ($this->groupBy === GroupBy::None) {
            return array_map(
                static fn (PresentedZone $zone): array => $zone->toArray(),
                $this->present(),
            );
        }

        $groups = [];

        foreach ($this->zones() as $zone) {
            $groups[$this->groupFor($zone)][] = $this->presentOne($zone)->toArray();
        }

        ksort($groups);

        return $groups;
    }

    /**
     * The same structure a form component wants: `value`/`label` pairs with their group and
     * direction, which is the shape almost every JS select library accepts.
     *
     * @return list<array<string, scalar|null>>
     */
    #[NoDiscard]
    public function forFormComponent(): array
    {
        $options = [];

        foreach ($this->zones() as $zone) {
            $presented = $this->presentOne($zone)->toArray();

            $options[] = [
                'value' => $zone->identifier,
                'label' => $this->renderLabel($zone),
                'group' => $this->groupBy === GroupBy::None ? null : $this->groupFor($zone),
                'dir' => $this->rtl ? 'rtl' : 'ltr',
                ...$presented,
            ];
        }

        return $options;
    }

    /** @return list<PresentedZone> */
    #[NoDiscard]
    public function forObjects(): array
    {
        return $this->present();
    }

    /** @throws JsonException */
    #[NoDiscard]
    public function forJson(int $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES): string
    {
        return json_encode($this->forApi(), $flags | JSON_THROW_ON_ERROR);
    }

    /**
     * Bare identifiers, for a validation rule or an `in:` list.
     *
     * @return list<string>
     */
    #[NoDiscard]
    public function forIdentifiers(): array
    {
        return array_map(static fn (Timezone $zone): string => $zone->identifier, $this->zones());
    }

    public function count(): int
    {
        return count($this->zones());
    }

    // ── internals ───────────────────────────────────────────────────────────────────────────

    /** @return list<Timezone> */
    private function zones(): array
    {
        $query = $this->at instanceof DateTimeInterface ? $this->query->asOf($this->at) : $this->query;

        return $query->orderBy(TimezoneField::Offset)->get()->all();
    }

    /** @return list<PresentedZone> */
    private function present(): array
    {
        return array_map($this->presentOne(...), $this->zones());
    }

    private function presentOne(Timezone $zone): PresentedZone
    {
        $fields = [];

        foreach ($this->fields as $field) {
            $fields[$field->value] = $this->valueFor($field, $zone);
        }

        return new PresentedZone($zone->identifier, $fields);
    }

    private function valueFor(ZoneField $field, Timezone $zone): string|int|float|bool|null
    {
        $location = $zone->location();

        return match ($field) {
            ZoneField::Id => $zone->identifier,
            ZoneField::Label => $this->renderLabel($zone),
            ZoneField::City => $zone->city(),
            ZoneField::Country => $location?->countryCode,
            ZoneField::CountryName => $this->countryName($location?->countryCode),
            ZoneField::Continent => $zone->region()?->value,
            ZoneField::Offset => $zone->offset($this->at)->seconds,
            ZoneField::OffsetLabel => $zone->offset($this->at)->format($this->offsetFormat),
            ZoneField::Abbreviation => $zone->abbreviation($this->at),
            ZoneField::Dst => $zone->isDst($this->at),
            ZoneField::ObservesDst => $zone->observesDst(),
            ZoneField::LocalTime => $this->localTime($zone),
            ZoneField::Latitude => $location?->latitude,
            ZoneField::Longitude => $location?->longitude,
            ZoneField::Flag => $this->flag($location?->countryCode),
            ZoneField::Search => $this->searchTokens($zone),
            ZoneField::Dir => $this->rtl ? 'rtl' : 'ltr',
            ZoneField::Deprecated => $zone->isDeprecated(),
        };
    }

    private function renderLabel(Timezone $zone): string
    {
        $location = $zone->location();
        $offset = $zone->offset($this->at);

        return strtr($this->labelTemplate, [
            '{id}' => $zone->identifier,
            '{city}' => $zone->city(),
            '{country}' => $location->countryCode ?? '',
            '{country_name}' => $this->countryName($location?->countryCode) ?? '',
            '{continent}' => $zone->region()->value ?? '',
            '{gmt}' => $offset->format($this->offsetFormat),
            '{offset}' => $offset->format(OffsetFormat::Colon),
            '{abbr}' => $zone->abbreviation($this->at),
            '{time}' => $this->localTime($zone),
            '{flag}' => $this->flag($location?->countryCode) ?? '',
        ]);
    }

    private function groupFor(Timezone $zone): string
    {
        return match ($this->groupBy) {
            GroupBy::Continent => $zone->region()->value ?? $this->catchAllGroup,
            GroupBy::Country => $this->countryName($zone->location()?->countryCode)
                ?? $zone->location()->countryCode
                ?? $this->catchAllGroup,
            GroupBy::Offset => $zone->offset($this->at)->format($this->offsetFormat),
            GroupBy::None => $this->catchAllGroup,
        };
    }

    /**
     * The wall-clock reading in this zone.
     *
     * Falls back to the zone's own current offset rather than reading a clock: the presenter has no
     * clock of its own, and `asOf()` is the supported way to pin the instant.
     */
    private function localTime(Timezone $zone): string
    {
        $instant = $this->at ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));

        return $zone->convert($instant)->format($this->timeFormat);
    }

    private function searchTokens(Timezone $zone): string
    {
        $location = $zone->location();

        return strtolower(implode(' ', array_filter(
            [
                $zone->identifier,
                $zone->city(),
                $location?->countryCode,
                $this->countryName($location?->countryCode),
                $zone->abbreviation($this->at),
                $zone->offset($this->at)->format(OffsetFormat::Colon),
            ],
            static fn (?string $token): bool => $token !== null && $token !== '',
        )));
    }

    /**
     * A localised country name, when ICU can supply one.
     *
     * `Locale::getDisplayRegion` wants a full tag, so the code is prefixed with a hyphen. Returns
     * null rather than the raw code when ICU has nothing, so a caller can tell the difference
     * between "Kenya" and "we only know KE".
     */
    private function countryName(?string $countryCode): ?string
    {
        if ($countryCode === null || ! class_exists(Locale::class)) {
            return null;
        }

        $name = Locale::getDisplayRegion('-' . $countryCode, $this->locale ?? 'en');

        if (! is_string($name) || $name === '' || $name === $countryCode) {
            return null;
        }

        return $name;
    }

    /**
     * The flag emoji for an ISO 3166-1 alpha-2 code.
     *
     * Computed, not tabled: a flag is the two letters shifted into the Regional Indicator Symbol
     * block, so every present and future country works without a lookup table to maintain.
     */
    private function flag(?string $countryCode): ?string
    {
        if ($countryCode === null || strlen($countryCode) !== 2) {
            return null;
        }

        $code = strtoupper($countryCode);
        $flag = '';

        foreach (str_split($code) as $letter) {
            $ordinal = ord($letter[0]);

            if ($ordinal < 65 || $ordinal > 90) {
                return null;
            }

            $flag .= mb_chr(0x1F1E6 + ($ordinal - 65), 'UTF-8');
        }

        return $flag;
    }

    private function isRightToLeft(string $locale): bool
    {
        return function_exists('locale_is_right_to_left')
            && locale_is_right_to_left($locale);
    }
}
