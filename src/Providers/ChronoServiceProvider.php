<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Providers;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Override;
use Psr\Clock\ClockInterface;
use Simtabi\Laranail\Chrono\Chrono;
use Simtabi\Laranail\Chrono\Console\DoctorCommand;
use Simtabi\Laranail\Chrono\Console\ListTimezonesCommand;
use Simtabi\Laranail\Chrono\Console\ShowTimezoneCommand;
use Simtabi\Laranail\Chrono\Console\SyncCommand;
use Simtabi\Laranail\Chrono\Core\Config\CatalogueOptions;
use Simtabi\Laranail\Chrono\Core\Config\DisplayOptions;
use Simtabi\Laranail\Chrono\Core\Config\DstPolicy;
use Simtabi\Laranail\Chrono\Core\Contracts\Clock;
use Simtabi\Laranail\Chrono\Core\Contracts\TimezoneRepository;
use Simtabi\Laranail\Chrono\Core\Format\DateFormatter;
use Simtabi\Laranail\Chrono\Core\Format\DateParser;
use Simtabi\Laranail\Chrono\Core\Humanize\Humanizer;
use Simtabi\Laranail\Chrono\Core\Timezone\Repository\CachedTimezoneRepository;
use Simtabi\Laranail\Chrono\Core\Timezone\Repository\PhpTimezoneRepository;
use Simtabi\Laranail\Chrono\Core\Timezone\Resolver\AliasResolver;
use Simtabi\Laranail\Chrono\Core\Timezone\Resolver\CountryResolver;
use Simtabi\Laranail\Chrono\Core\Timezone\Resolver\ResolverChain;
use Simtabi\Laranail\Chrono\Core\Timezone\Support\SystemClock;
use Simtabi\Laranail\Chrono\Core\Timezone\Support\TransitionScanner;
use Simtabi\Laranail\Chrono\Core\Timezone\Timezones;
use Simtabi\Laranail\Package\Tools\Package;
use Simtabi\Laranail\Package\Tools\Providers\PackageServiceProvider;

/**
 * Entry point for laranail/chrono.
 *
 * Configuration is vendor-namespaced, per the laranail convention: the file publishes to
 * `config/laranail/chrono.php` and application code reads `config('laranail.chrono.*')`, matching
 * the `laranail::chrono.<command>` shape commands use. Publish tags are `laranail::chrono-*`.
 *
 * @internal Auto-discovered framework wiring; not part of the public API.
 */
final class ChronoServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laranail/chrono')
            ->setPublishTagId('chrono')
            ->hasConfigFile('chrono')
            ->hasTranslations('chrono')
            ->hasViews('chrono')
            ->hasBladeComponentNamespace('Simtabi\\Laranail\\Chrono\\View\\Components', 'chrono')
            ->hasCommands([
                ListTimezonesCommand::class,
                ShowTimezoneCommand::class,
                DoctorCommand::class,
                SyncCommand::class,
            ]);
    }

    #[Override]
    public function packageRegistered(): void
    {
        // Bound with bindIf, never singleton: a host application or a test may already have bound
        // its own PSR-20 clock, and clobbering it would silently un-freeze time in their suite.
        $this->app->bindIf(ClockInterface::class, SystemClock::class, shared: true);
        $this->app->bindIf(Clock::class, SystemClock::class, shared: true);

        $this->app->singleton(TransitionScanner::class, static fn (): TransitionScanner => new TransitionScanner);

        $this->app->singleton(
            TimezoneRepository::class,
            static fn (): TimezoneRepository => new PhpTimezoneRepository,
        );

        $this->app->singleton(Timezones::class, function (): Timezones {
            /** @var array<string, mixed> $resolution */
            $resolution = (array) config('laranail.chrono.resolution', []);

            /** @var array<string, string> $aliasOverrides */
            $aliasOverrides = (array) ($resolution['aliases'] ?? []);

            /** @var array<string, string> $countryDefaults */
            $countryDefaults = (array) ($resolution['country_defaults'] ?? []);

            $chain = ResolverChain::default(
                new AliasResolver($aliasOverrides),
                new CountryResolver($countryDefaults),
            );

            /** @var list<string> $strategies */
            $strategies = (array) ($resolution['strategies'] ?? []);

            if ($strategies !== []) {
                $chain = $chain->only(...$strategies);
            }

            return new Timezones(
                repository: $this->app->make(TimezoneRepository::class),
                resolver: $chain,
                scanner: $this->app->make(TransitionScanner::class),
                clock: $this->app->make(ClockInterface::class),
                fallback: (string) config('laranail.chrono.fallback', 'UTC'),
                strict: (bool) ($resolution['strict'] ?? true),
                preferredCountries: array_values((array) ($resolution['preferred_countries'] ?? [])),
                allowAbbreviations: (bool) ($resolution['abbreviations'] ?? false),
                catalogue: CatalogueOptions::fromArray((array) config('laranail.chrono.catalogue', [])),
                dst: $this->app->make(DstPolicy::class),
                canonicaliseAliases: (bool) ($resolution['canonicalise'] ?? true),
            );
        });

        // Two settings the whole package reads, bound once so a host can rebind either in a test
        // without reaching into every consumer that happens to render a date.
        $this->app->singleton(DstPolicy::class, static fn (): DstPolicy => DstPolicy::fromArray(
            (array) config('laranail.chrono.dst', []),
        ));

        $this->app->singleton(DisplayOptions::class, static fn (): DisplayOptions => DisplayOptions::fromArray([
            ...(array) config('laranail.chrono.display', []),
            'locale' => config('laranail.chrono.display.locale') ?? config('app.locale', 'en'),
        ]));

        $this->app->singleton(DateFormatter::class, static fn (): DateFormatter => new DateFormatter(
            (string) (config('laranail.chrono.display.locale') ?? config('app.locale', 'en')),
        ));

        $this->app->singleton(DateParser::class, static fn (): DateParser => new DateParser(
            strict: (bool) config('laranail.chrono.resolution.strict', true),
        ));

        $this->app->singleton(Humanizer::class, fn (): Humanizer => new Humanizer(
            clock: $this->app->make(ClockInterface::class),
            defaultLocale: (string) (config('laranail.chrono.display.locale') ?? config('app.locale', 'en')),
        ));

        $this->app->singleton(Chrono::class, fn (): Chrono => new Chrono(
            $this->app->make(Timezones::class),
            $this->app->make(DateFormatter::class),
            $this->app->make(DateParser::class),
            $this->app->make(Humanizer::class),
            $this->app->make(DisplayOptions::class),
        ));

        $this->app->alias(Timezones::class, 'laranail.chrono.timezones');
        $this->app->alias(Chrono::class, 'laranail.chrono');
    }

    #[Override]
    public function packageBooted(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../resources/js' => public_path('vendor/laranail/chrono'),
            ], 'laranail::chrono-assets');
        }

        // Decoration happens here, not in packageRegistered(), because config is only merged after
        // registration — a Testbench host's getEnvironmentSetUp() override must still win.
        if (! (bool) config('laranail.chrono.cache.enabled', true)) {
            return;
        }

        // Laravel's cache repository already implements PSR-16, so no adapter is needed.
        $this->app->extend(TimezoneRepository::class, function (TimezoneRepository $repository): TimezoneRepository {
            $store = config('laranail.chrono.cache.store');

            /** @var CacheRepository $cache */
            $cache = $this->app->make('cache')->store(is_string($store) ? $store : null);

            return new CachedTimezoneRepository(
                $repository,
                $cache,
                (string) config('laranail.chrono.cache.prefix', 'laranail.chrono'),
                (int) config('laranail.chrono.cache.ttl', 86400),
            );
        });
    }
}
