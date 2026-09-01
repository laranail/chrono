<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Config;

use NoDiscard;
use Simtabi\Laranail\Chrono\Core\Enums\TimezoneField;
use Simtabi\Laranail\Chrono\Core\Timezone\Query\TimezoneQuery;

/**
 * Which zones this application offers.
 *
 * Applied to every query the service hands out, so the picker, the API and the validation rules all
 * see the same set. Without it `AllowedTimezone` validates against all 419 while the picker shows
 * forty, and the disagreement is invisible until a user submits a zone the form never offered.
 */
final readonly class CatalogueOptions
{
    /**
     * @param  list<string>  $only
     * @param  list<string>  $except
     * @param  list<string>  $countries
     */
    public function __construct(
        public bool $includeDeprecated = false,
        public bool $includeFixed = false,
        public bool $includeUtc = true,
        public array $only = [],
        public array $except = [],
        public array $countries = [],
        public TimezoneField $sort = TimezoneField::Offset,
        public bool $sortDescending = false,
    ) {}

    /** @param array<string, mixed> $config */
    public static function fromArray(array $config): self
    {
        /** @var list<string> $only */
        $only = array_values((array) ($config['only'] ?? []));
        /** @var list<string> $except */
        $except = array_values((array) ($config['except'] ?? []));
        /** @var list<string> $countries */
        $countries = array_values((array) ($config['countries'] ?? []));

        // A leading `-` reverses the order, so `-offset` needs no second setting to mean anything.
        $sort = is_string($config['sort'] ?? null) ? $config['sort'] : TimezoneField::Offset->value;
        $descending = str_starts_with($sort, '-');

        return new self(
            includeDeprecated: (bool) ($config['include_deprecated'] ?? false),
            includeFixed: (bool) ($config['include_fixed'] ?? false),
            includeUtc: (bool) ($config['include_utc'] ?? true),
            only: $only,
            except: $except,
            countries: $countries,
            sort: TimezoneField::tryFrom(ltrim($sort, '-')) ?? TimezoneField::Offset,
            sortDescending: $descending,
        );
    }

    #[NoDiscard]
    public function applyTo(TimezoneQuery $query): TimezoneQuery
    {
        $query = $query
            ->includeDeprecated($this->includeDeprecated)
            ->includeFixed($this->includeFixed)
            ->includeUtc($this->includeUtc);

        if ($this->countries !== []) {
            $query = $query->inCountry(...$this->countries);
        }

        if ($this->only !== []) {
            $query = $query->only(...$this->only);
        }

        if ($this->except !== []) {
            $query = $query->except(...$this->except);
        }

        return $query->orderBy($this->sort, $this->sortDescending);
    }

    public function isUnrestricted(): bool
    {
        return $this->only === [] && $this->except === [] && $this->countries === []
            && ! $this->includeDeprecated && ! $this->includeFixed && $this->includeUtc;
    }
}
