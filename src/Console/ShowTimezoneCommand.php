<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Console;

use Override;
use Simtabi\Laranail\Chrono\Core\Enums\OffsetFormat;
use Simtabi\Laranail\Chrono\Core\Timezone\Timezones;
use Simtabi\Laranail\Chrono\Core\Timezone\Value\Timezone;
use Simtabi\Laranail\Chrono\Core\Timezone\Value\Transition;
use Simtabi\Laranail\Console\Tools\Commands\Command;
use Simtabi\Laranail\Console\Tools\Commands\Concerns\SupportsNamespacedNames;

/**
 * Everything known about one zone — the command you run when a timestamp looks wrong.
 *
 * Accepts anything the resolver does, so `chrono:show KE` and `chrono:show "Pacific Standard Time"`
 * both work, and the output names what the input actually resolved to.
 */
final class ShowTimezoneCommand extends Command
{
    use SupportsNamespacedNames;

    /** @var list<string> */
    #[Override]
    protected array $commandAliases = ['chrono:show'];

    #[Override]
    protected $signature = 'laranail::chrono.show {zone : An identifier, alias, offset, country code or Windows id}';

    #[Override]
    protected $description = 'Show offsets, daylight saving and transitions for one timezone.';

    public function handle(Timezones $timezones): int
    {
        /** @var string $input */
        $input = $this->argument('zone');
        $zone = $timezones->tryOf($input);

        if (! $zone instanceof Timezone) {
            $this->components->error(sprintf('Could not resolve "%s" to a timezone.', $input));

            $candidates = $timezones->lenient()->candidates($input);

            if ($candidates->isNotEmpty()) {
                $this->line('  Did you mean: ' . implode(', ', array_slice($candidates->identifiers(), 0, 5)));
            }

            return self::FAILURE;
        }

        $offset = $zone->offset();
        $next = $zone->nextTransition();
        $previous = $zone->previousTransition();

        $rows = [
            ['Identifier', $zone->identifier],
            ['Resolved from', $input === $zone->identifier ? '—' : $input],
            ['Canonical', $zone->canonicalIdentifier()],
            ['Kind', $zone->kind->value],
            ['City', $zone->city()],
            ['Country', $zone->countryCode() ?? '—'],
            ['Continent', $zone->region()->value ?? '—'],
            ['Offset now', $offset->format(OffsetFormat::Utc) . '  (' . $offset->seconds . 's)'],
            ['Abbreviation', $zone->abbreviation()],
            ['DST in effect', $zone->isDst() ? 'yes' : 'no'],
            ['Observes DST', $zone->observesDst() ? 'yes' : 'no'],
            ['DST shift', $zone->observesDst() ? $zone->dstSavings()->format(OffsetFormat::Short) : '—'],
            ['Local time', $zone->convert($timezones->now())->format('Y-m-d H:i:s')],
            ['Previous change', $previous instanceof Transition ? $previous->at->format('Y-m-d H:i') . ' UTC' : '—'],
            ['Next change', $next instanceof Transition ? $next->at->format('Y-m-d H:i') . ' UTC' : '—'],
        ];

        $this->table(['', $zone->identifier], $rows);

        return self::SUCCESS;
    }
}
