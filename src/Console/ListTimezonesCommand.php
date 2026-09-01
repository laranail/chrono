<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Console;

use Override;
use Simtabi\Laranail\Chrono\Core\Enums\GroupBy;
use Simtabi\Laranail\Chrono\Core\Enums\PresentationPreset;
use Simtabi\Laranail\Chrono\Core\Enums\ZoneField;
use Simtabi\Laranail\Chrono\Core\Presentation\TimezonePresenter;
use Simtabi\Laranail\Chrono\Core\Timezone\Timezones;
use Simtabi\Laranail\Console\Tools\Commands\Command;
use Simtabi\Laranail\Console\Tools\Commands\Concerns\SupportsNamespacedNames;

/**
 * The catalogue as this application has actually configured it.
 *
 * Worth having because the configured catalogue and the full 419 are rarely the same, and a
 * mismatch between what the picker offers and what validation accepts is invisible until a user
 * hits it.
 */
final class ListTimezonesCommand extends Command
{
    use SupportsNamespacedNames;

    /** @var list<string> */
    #[Override]
    protected array $commandAliases = ['chrono:list'];

    #[Override]
    protected $signature = 'laranail::chrono.list
        {--region= : Restrict to a continent, e.g. Africa}
        {--country=* : Restrict to ISO 3166-1 alpha-2 codes}
        {--search= : Match identifier, city or country}
        {--group=none : none|continent|country|offset}
        {--format=table : table|json|csv|ids}';

    public function handle(Timezones $timezones): int
    {
        $query = $timezones->query();

        if (is_string($region = $this->option('region')) && $region !== '') {
            $query = $query->inRegion($region);
        }

        /** @var list<string> $countries */
        $countries = (array) $this->option('country');

        if ($countries !== []) {
            $query = $query->inCountry(...$countries);
        }

        if (is_string($search = $this->option('search')) && $search !== '') {
            $query = $query->matching($search);
        }

        $presenter = new TimezonePresenter($query)
            ->preset(PresentationPreset::Api)
            ->groupBy(GroupBy::tryFrom($this->stringOption('group')) ?? GroupBy::None);

        if ($presenter->count() === 0) {
            $this->components->warn('No timezone matched.');

            return self::SUCCESS;
        }

        match ($this->stringOption('format')) {
            'json' => $this->line($presenter->forJson(JSON_PRETTY_PRINT)),
            'ids' => $this->line(implode(PHP_EOL, $presenter->forIdentifiers())),
            'csv' => $this->csv($presenter),
            default => $this->rows($presenter),
        };

        return self::SUCCESS;
    }

    /** Options are declared with defaults, so this only narrows the type for the analyser. */
    private function stringOption(string $name): string
    {
        $value = $this->option($name);

        return is_string($value) ? $value : '';
    }

    private function rows(TimezonePresenter $presenter): void
    {
        $rows = [];

        foreach ($presenter->flat()->forObjects() as $zone) {
            $rows[] = [
                $zone->id,
                (string) $zone->get(ZoneField::OffsetLabel),
                (string) $zone->get(ZoneField::Abbreviation),
                $zone->get(ZoneField::Dst) === true ? 'yes' : 'no',
                (string) $zone->get(ZoneField::Country),
                (string) $zone->get(ZoneField::LocalTime),
            ];
        }

        $this->table(['Identifier', 'Offset', 'Abbr', 'DST', 'Country', 'Local'], $rows);
        $this->components->info(sprintf('%d zone(s).', count($rows)));
    }

    private function csv(TimezonePresenter $presenter): void
    {
        $rows = $presenter->flat()->forApi();

        if ($rows === []) {
            return;
        }

        /** @var array<string, scalar|null> $first */
        $first = $rows[0];
        $this->line(implode(',', array_keys($first)));

        foreach ($rows as $row) {
            /** @var array<string, scalar|null> $row */
            $this->line(implode(',', array_map(
                static fn (mixed $v): string => '"'.str_replace('"', '""', (string) $v).'"',
                $row,
            )));
        }
    }
}
