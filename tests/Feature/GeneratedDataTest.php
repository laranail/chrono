<?php

declare(strict_types=1);

use Simtabi\Laranail\Chrono\Core\Timezone\Support\AliasMap;

/*
|--------------------------------------------------------------------------
| Generated data is a function of tzdata alone
|--------------------------------------------------------------------------
|
| Which means any difference between what is committed and what the generator
| emits is a signal, not noise: either the database moved under us, or someone
| hand-edited a file that says not to.
|
*/

it('regenerates byte for byte against the runner\'s database', function (string $generator): void {
    $script = dirname(__DIR__, 2) . '/tools/' . $generator;

    exec(sprintf('%s %s --check 2>&1', escapeshellarg(PHP_BINARY), escapeshellarg($script)), $output, $exitCode);

    expect($exitCode)->toBe(0, implode("\n", $output));
})->with([
    'alias map' => ['generate-alias-map.php'],
    'enums' => ['generate-enums.php'],
]);

/**
 * An alias is an exact link, so its target must share its rules. This is what stops a curated entry
 * from being merely plausible — a wrong mapping fails here rather than silently shipping.
 */
it('maps every alias to a target with identical rules', function (): void {
    $canonical = DateTimeZone::listIdentifiers(DateTimeZone::ALL);

    $rules = static function (string $identifier): string {
        $transitions = new DateTimeZone($identifier)->getTransitions(0, 2145916800);

        return hash('xxh128', implode(',', array_map(
            static fn (array $t): string => $t['ts'] . ':' . $t['offset'] . ':' . (int) $t['isdst'],
            $transitions === false ? [] : $transitions,
        )));
    };

    foreach (AliasMap::all() as $alias => $target) {
        expect($canonical)->toContain($target)
            ->and($rules($alias))->toBe($rules($target), "{$alias} and {$target} have different rules");
    }
});

/**
 * These look like aliases of UTC and are not. `GMT` and `UCT` are abbreviation zones, `GMT+0` and
 * `GMT-0` are offset zones whose name normalises to `+00:00`, and all four return false from both
 * getTransitions() and getLocation(). The region-style spellings do behave like zones.
 */
it('excludes identifiers that only look like aliases', function (string $identifier): void {
    expect(AliasMap::isAlias($identifier))->toBeFalse();
})->with(['GMT', 'GMT+0', 'GMT-0', 'UCT', 'CET', 'EST', 'MST7MDT', 'Etc/GMT+5', 'Etc/Greenwich']);

it('does map the region-style spellings of UTC', function (string $identifier): void {
    expect(AliasMap::canonical($identifier))->toBe('UTC');
})->with(['GMT0', 'Greenwich', 'Universal', 'Zulu']);
