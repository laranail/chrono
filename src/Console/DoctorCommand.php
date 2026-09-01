<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Console;

use IntlTimeZone;
use Override;
use Simtabi\Laranail\Chrono\Core\Config\CatalogueOptions;
use Simtabi\Laranail\Chrono\Core\Config\DstPolicy;
use Simtabi\Laranail\Chrono\Core\Timezone\Timezones;
use Simtabi\Laranail\Chrono\Core\Timezone\Value\Timezone;
use Simtabi\Laranail\Console\Tools\Commands\Command;
use Simtabi\Laranail\Console\Tools\Commands\Concerns\SupportsNamespacedNames;

/**
 * "Is this host's date data trustworthy?" — the first thing to run when a timestamp is wrong on one
 * machine and right on another.
 *
 * The checks that matter are the ones nobody thinks to make: whether the tz database is years stale,
 * and whether ICU's copy agrees with PHP's. They ship independently and routinely disagree — a
 * machine was observed running PHP tzdata 2025.3 against ICU 2019a, which is a six-year gap in the
 * data behind every localised zone name.
 */
final class DoctorCommand extends Command
{
    use SupportsNamespacedNames;

    /** @var list<string> */
    #[Override]
    protected array $commandAliases = ['chrono:doctor'];

    #[Override]
    protected $signature = 'laranail::chrono.doctor {--strict : Treat warnings as failures, as doctor.strict does permanently}';

    #[Override]
    protected $description = 'Check the health of this host\'s timezone data and configuration.';

    public function handle(Timezones $timezones): int
    {
        $failed = false;
        $warned = false;

        $phpTzdata = $timezones->version();
        $minimum = (string) config('laranail.chrono.doctor.min_tzdata', '2024.1');

        $this->components->info('Date data');

        // A system-tzdata build reports `0.system` rather than a release. Comparing that against a
        // minimum would warn on every well-maintained Debian host in existence, so say what is
        // actually true instead: the data comes from the OS, and the OS is what keeps it current.
        if ($timezones->usesSystemDatabase()) {
            $this->components->twoColumnDetail('PHP tzdata', 'from the OS ('.$phpTzdata.')');
            $this->components->twoColumnDetail(
                '',
                '<fg=gray>keep it current with the system package, not with PHP</>',
            );
        } else {
            $this->components->twoColumnDetail('PHP tzdata', $phpTzdata);
        }

        if (! $timezones->usesSystemDatabase()
            && version_compare($this->comparable($phpTzdata), $this->comparable($minimum), '<')) {
            $this->components->warn(sprintf(
                'tzdata %s is older than the configured minimum of %s. Zones change several times a '
                .'year by decree; this host is likely wrong about at least one country.',
                $phpTzdata,
                $minimum,
            ));
            $warned = true;
        }

        if (class_exists(IntlTimeZone::class)) {
            $icu = IntlTimeZone::getTZDataVersion();
            $icuVersion = defined('INTL_ICU_VERSION') ? INTL_ICU_VERSION : 'unknown';

            $this->components->twoColumnDetail('ICU', (string) $icuVersion);
            $this->components->twoColumnDetail('ICU tzdata', $icu === false ? 'unknown' : $icu);

            if (is_string($icu) && $icu !== '' && ! $timezones->usesSystemDatabase()
                && (bool) config('laranail.chrono.doctor.warn_on_icu_drift', true)) {
                if ($this->comparable($icu) !== $this->comparable($phpTzdata)) {
                    $this->components->warn(sprintf(
                        'ICU carries tzdata %s while PHP carries %s. Localised names come from ICU and '
                        .'offsets from PHP, so the two can disagree about the same zone.',
                        $icu,
                        $phpTzdata,
                    ));
                    $warned = true;
                }
            }
        } else {
            $this->components->error('ext-intl is not loaded; it is a hard requirement.');
            $failed = true;
        }

        $this->components->info('Catalogue');

        $catalogue = CatalogueOptions::fromArray((array) config('laranail.chrono.catalogue', []));
        $offered = $timezones->query()->count();

        $this->components->twoColumnDetail('Zones offered', (string) $offered);

        if (! $catalogue->isUnrestricted()) {
            $total = $timezones->unrestrictedQuery()->count();
            $this->components->twoColumnDetail('Zones known', (string) $total);

            if ($offered === 0) {
                $this->components->error(
                    'The configured catalogue matches no zones at all, so every picker is empty and '
                    .'every timezone_allowed rule rejects everything. Check catalogue.only and '
                    .'catalogue.countries.',
                );
                $failed = true;
            }
        }

        $this->components->twoColumnDetail('Aliases', (string) count($timezones->aliases()));
        $this->components->twoColumnDetail('Cache fingerprint', $timezones->fingerprint());

        $this->components->info('Configuration');

        // The setting the package exists for. Reported unconditionally, because the default
        // reproduces PHP's silent resolution and an application that never chose is worth telling.
        $dst = DstPolicy::fromArray((array) config('laranail.chrono.dst', []));

        $this->components->twoColumnDetail('On a DST gap', $dst->gap->value);
        $this->components->twoColumnDetail('On an ambiguous time', $dst->ambiguity->value);

        if (! $dst->isStrict()) {
            $this->components->twoColumnDetail(
                '',
                '<fg=gray>set dst.on_gap and dst.on_ambiguous to `throw` for bookings or payroll</>',
            );
        }

        foreach (['default', 'fallback'] as $key) {
            $value = (string) config('laranail.chrono.'.$key, 'UTC');
            $resolved = $timezones->tryOf($value);

            $this->components->twoColumnDetail($key, $value.($resolved instanceof Timezone ? '' : ' — UNRESOLVABLE'));

            if (! $resolved instanceof Timezone) {
                $failed = true;
            }
        }

        // Storage assumes the process default matches app.timezone; moving one without the other
        // silently shifts every timestamp Eloquent writes.
        $processDefault = date_default_timezone_get();
        $appTimezone = (string) config('app.timezone', 'UTC');

        $this->components->twoColumnDetail('app.timezone', $appTimezone);
        $this->components->twoColumnDetail('Process default', $processDefault);

        if ($processDefault !== $appTimezone) {
            $this->components->warn(sprintf(
                'The process default (%s) differs from app.timezone (%s). Eloquent assumes they match.',
                $processDefault,
                $appTimezone,
            ));
            $warned = true;
        }

        $strict = (bool) $this->option('strict') || (bool) config('laranail.chrono.doctor.strict', false);

        if ($failed || ($warned && $strict)) {
            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info($warned ? 'Healthy, with warnings.' : 'Healthy.');

        return self::SUCCESS;
    }

    /** tzdata releases are `2025.3`-style; pad so `2025.3` sorts below `2025.10`. */
    private function comparable(string $version): string
    {
        [$year, $release] = array_pad(explode('.', $version, 2), 2, '0');

        return $year.'.'.str_pad(preg_replace('/\D/', '', $release) ?: '0', 3, '0', STR_PAD_LEFT);
    }
}
