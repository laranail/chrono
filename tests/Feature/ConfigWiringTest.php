<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Simtabi\Laranail\Chrono\Chrono as ChronoService;
use Simtabi\Laranail\Chrono\Core\Concerns\PresentsTimezones;
use Simtabi\Laranail\Chrono\Core\Config\DisplayOptions;
use Simtabi\Laranail\Chrono\Core\Config\DstPolicy;
use Simtabi\Laranail\Chrono\Core\Config\SelectOptions;
use Simtabi\Laranail\Chrono\Core\Enums\GapPolicy;
use Simtabi\Laranail\Chrono\Core\Enums\SelectShape;
use Simtabi\Laranail\Chrono\Core\Exception\AmbiguousLocalTime;
use Simtabi\Laranail\Chrono\Core\Exception\SkippedLocalTime;
use Simtabi\Laranail\Chrono\Core\Timezone\Timezones as TimezonesService;
use Simtabi\Laranail\Chrono\Facades\Chrono;

/**
 * A configuration key that nothing reads is worse than one that does not exist.
 *
 * The audit before v0.1.0 found a documented `catalogue` section that no code consulted: the picker
 * showed forty zones while `AllowedTimezone` accepted all 419, and the disagreement was invisible
 * until a user submitted a zone the form never offered. These tests exist so that class of bug
 * cannot come back quietly — each one changes a setting and asserts the behaviour it promises.
 */

/** Rebuild the singletons, which is what a real application gets on its next boot. */
function rebootChrono(): void
{
    foreach ([TimezonesService::class, ChronoService::class, DstPolicy::class, DisplayOptions::class] as $service) {
        app()->forgetInstance($service);
    }
}

describe('every documented key is reachable from the code', function (): void {
    /**
     * The guard the audit wished it had.
     *
     * Not a proof of correct behaviour — the tests below are that — but a proof that no key was
     * shipped as documentation only. Adding a setting to the config file without a consumer fails
     * here immediately, rather than a year later when someone notices it never did anything.
     */
    it('names no setting the source never mentions', function (): void {
        $source = collect(
            (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/src')))
        )->filter(static fn (SplFileInfo $file): bool => $file->getExtension() === 'php')
            ->map(static fn (SplFileInfo $file): string => (string) file_get_contents($file->getPathname()))
            ->implode("\n");

        $unread = [];

        foreach (leafKeys((array) config('laranail.chrono')) as $path) {
            $leaf = array_last(explode('.', $path));

            // Either the whole path (`config('laranail.chrono.cache.ttl')`) or the leaf as an array
            // key inside a value object's fromArray() counts as a consumer.
            if (! str_contains($source, $path) && ! str_contains($source, "'" . $leaf . "'")) {
                $unread[] = $path;
            }
        }

        expect($unread)->toBe([], 'These keys are documented but nothing reads them: ' . implode(', ', $unread));
    });
});

/**
 * `ServiceResolver` returns null for anything the container does not hold, and every trait then
 * quietly constructs a default. That is the right behaviour outside a framework and a trap inside
 * one: a service nobody bound makes the trait ignore configuration while still working, which is
 * indistinguishable from working correctly.
 *
 * `SelectOptions` was exactly that. `PresentsTimezones::zoneOptions()` documented itself as
 * returning "the configured picker shape" and returned the default one, because the provider bound
 * every other value object and not that one.
 */
it('binds every service the traits look up', function (): void {
    $concerns = glob(dirname(__DIR__, 2) . '/src/Core/Concerns/*.php') ?: [];
    $unbound = [];

    foreach ($concerns as $file) {
        $source = (string) file_get_contents($file);

        preg_match_all('/ServiceResolver::resolve\((\w+)::class\)/', $source, $lookups);
        preg_match_all('/^use ([^;]+);$/m', $source, $imports);

        $fqcn = [];

        foreach ($imports[1] as $import) {
            $fqcn[substr((string) strrchr('\\' . $import, '\\'), 1)] = $import;
        }

        foreach (array_unique($lookups[1]) as $short) {
            $class = $fqcn[$short] ?? null;

            if ($class !== null && ! app()->bound($class)) {
                $unbound[] = basename($file) . ' asks for ' . $short;
            }
        }
    }

    expect($unbound)->toBe([], implode('; ', $unbound));
});

describe('select.shape reaches the trait, not just the component', function (): void {
    it('is what the trait returns', function (): void {
        config()->set('laranail.chrono.select.shape', 'flat');
        app()->forgetInstance(SelectOptions::class);

        $consumer = new class
        {
            use PresentsTimezones;

            /** @return array<array-key, mixed> */
            public function options(): array
            {
                return $this->zoneOptions();
            }
        };

        // Flat means identifiers at the top level; grouped would key by continent.
        expect($consumer->options())->toHaveKey('Africa/Nairobi')
            ->and($consumer->options())->not->toHaveKey('Africa');
    });
});

describe('dst.on_gap and dst.on_ambiguous', function (): void {
    it('reaches a call site that never mentioned daylight saving', function (): void {
        config()->set('laranail.chrono.dst.on_gap', 'throw');
        config()->set('laranail.chrono.dst.on_ambiguous', 'throw');
        rebootChrono();

        $newYork = app(TimezonesService::class)->of('America/New_York');

        // 02:30 on the spring-forward date never happened; 01:30 on the fall-back date happened
        // twice. Neither call passes a policy — that is the point.
        expect(fn () => $newYork->at('2026-03-08 02:30'))->toThrow(SkippedLocalTime::class)
            ->and(fn () => $newYork->at('2026-11-01 01:30'))->toThrow(AmbiguousLocalTime::class);
    });

    it('still lets one call site decide for itself', function (): void {
        config()->set('laranail.chrono.dst.on_gap', 'throw');
        rebootChrono();

        $forward = app(TimezonesService::class)
            ->of('America/New_York')
            ->at('2026-03-08 02:30', GapPolicy::Forward);

        expect($forward->format('H:i'))->toBe('03:30');
    });

    it('carries into the converter, which never asked for a policy', function (): void {
        config()->set('laranail.chrono.dst.on_ambiguous', 'throw');
        rebootChrono();

        expect(fn () => Chrono::convert('2026-11-01 01:30')->from('America/New_York')->to('UTC')->first())
            ->toThrow(AmbiguousLocalTime::class);
    });

    it('defaults to reproducing PHP, so adopting the package changes nothing', function (): void {
        $zone = app(TimezonesService::class)->of('America/New_York');

        expect($zone->at('2026-03-08 02:30')->format('H:i'))->toBe('03:30')
            ->and($zone->dst->isStrict())->toBeFalse();
    });
});

describe('display.offset_format and display.datetime_format', function (): void {
    it('renders the picker and the converter the same way', function (): void {
        config()->set('laranail.chrono.display.offset_format', 'colon');
        config()->set('laranail.chrono.display.datetime_format', 'D, d M Y H:i');
        rebootChrono();

        $converted = Chrono::convert('2026-06-15 12:00')->from('UTC')->to('Africa/Nairobi')->first();
        $options = Chrono::present()->groupByContinent()->forSelect();

        expect($converted?->formatted())->toBe('Mon, 15 Jun 2026 15:00')
            // The same offset, in the same shape, in a completely different subsystem.
            ->and($options['Africa']['Africa/Nairobi'])->toContain('+03:00')
            ->and($options['Africa']['Africa/Nairobi'])->not->toContain('UTC +03:00');
    });

    it('lets a call site override without changing the application default', function (): void {
        config()->set('laranail.chrono.display.datetime_format', 'D, d M Y H:i');
        rebootChrono();

        expect(Chrono::convert('2026-06-15 12:00')->from('UTC')->to('UTC')->format('Y-m-d')->first()?->formatted())
            ->toBe('2026-06-15')
            ->and(Chrono::display()->dateTimeFormat)->toBe('D, d M Y H:i');
    });
});

describe('select.shape and select.placeholder', function (): void {
    it('shapes the picker without the template restating it', function (): void {
        config()->set('laranail.chrono.select.shape', 'flat');
        config()->set('laranail.chrono.select.placeholder', 'Pick a zone');
        rebootChrono();

        $html = Blade::render('<x-laranail-chrono::timezone-select name="tz" />');

        expect($html)->toContain('Pick a zone')
            // Flat means no optgroups at all — the shape reached the markup, not just the presenter.
            ->and($html)->not->toContain('<optgroup');
    });

    it('lets one field override the application default', function (): void {
        config()->set('laranail.chrono.select.shape', 'flat');
        rebootChrono();

        expect(Blade::render('<x-laranail-chrono::timezone-select name="tz" shape="grouped" />'))
            ->toContain('<optgroup');
    });

    it('applies the shape wherever the presenter is used', function (): void {
        $formed = Chrono::present()->shape(SelectShape::Formed)->forSelect();
        $grouped = Chrono::present()->shape(SelectShape::Grouped)->forSelect();

        // `formed` labels with the full identifier, `grouped` with the city alone.
        expect($formed['Africa']['Africa/Nairobi'])->toStartWith('Africa/Nairobi')
            ->and($grouped['Africa']['Africa/Nairobi'])->toStartWith('Nairobi');
    });
});

describe('resolution.canonicalise', function (): void {
    it('rewrites a deprecated identifier by default', function (): void {
        expect(app(TimezonesService::class)->resolve('Asia/Calcutta'))->toBe('Asia/Kolkata');
    });

    it('keeps the input as written when turned off', function (): void {
        config()->set('laranail.chrono.resolution.canonicalise', false);
        rebootChrono();

        $timezones = app(TimezonesService::class);

        expect($timezones->resolve('Asia/Calcutta'))->toBe('Asia/Calcutta')
            ->and($timezones->of('Asia/Calcutta')->identifier)->toBe('Asia/Calcutta')
            // The zone still knows what it points at, and still compares equal to it.
            ->and($timezones->of('Asia/Calcutta')->canonicalIdentifier())->toBe('Asia/Kolkata')
            ->and($timezones->of('Asia/Calcutta')->equals('Asia/Kolkata'))->toBeTrue();
    });

    it('preserves only a real alias, never an abbreviation or a country', function (): void {
        config()->set('laranail.chrono.resolution.canonicalise', false);
        config()->set('laranail.chrono.resolution.abbreviations', true);
        rebootChrono();

        // `KE` is not an identifier, so there is nothing to preserve — it must still resolve.
        expect(app(TimezonesService::class)->resolve('KE'))->toBe('Africa/Nairobi');
    });

    it('leaves the explicit call alone, because that is what its name promises', function (): void {
        config()->set('laranail.chrono.resolution.canonicalise', false);
        rebootChrono();

        expect(app(TimezonesService::class)->canonicalise('Asia/Calcutta'))->toBe('Asia/Kolkata');
    });
});

describe('doctor.strict', function (): void {
    /**
     * The warning used is the process-default mismatch rather than a stale tzdata, because a
     * system-tzdata host has no version to be stale — and a test that only warns on some machines
     * is testing the machine.
     */
    it('turns a warning into a failure without the flag', function (): void {
        config()->set('app.timezone', 'Africa/Nairobi');
        config()->set('laranail.chrono.doctor.strict', true);

        $this->artisan('laranail::chrono.doctor')->assertExitCode(1);
    });

    it('reports a warning and still passes when lenient', function (): void {
        config()->set('app.timezone', 'Africa/Nairobi');
        config()->set('laranail.chrono.doctor.strict', false);

        $this->artisan('laranail::chrono.doctor')->assertExitCode(0);
    });

    it('fails outright when the catalogue matches nothing', function (): void {
        // An empty catalogue is not a warning: every picker is blank and every rule rejects
        // everything, which is a broken application rather than a stale one.
        config()->set('laranail.chrono.catalogue.only', ['Not/AZone']);
        rebootChrono();

        $this->artisan('laranail::chrono.doctor')->assertExitCode(1);
    });
});

/**
 * Flatten a config array to dotted leaf paths, treating a list as a leaf.
 *
 * @param array<string, mixed> $config
 * @return list<string>
 */
function leafKeys(array $config, string $prefix = ''): array
{
    $paths = [];

    foreach ($config as $key => $value) {
        $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;

        if (is_array($value) && $value !== [] && ! array_is_list($value)) {
            $paths = [...$paths, ...leafKeys($value, $path)];

            continue;
        }

        $paths[] = $path;
    }

    return $paths;
}
