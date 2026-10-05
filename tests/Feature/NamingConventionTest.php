<?php

declare(strict_types=1);

use Illuminate\Support\Facades\View;
use Simtabi\Laranail\Package\Tools\Testing\NamingScope;
use Simtabi\Laranail\Package\Tools\Testing\AssertsRegisteredNames;

uses(AssertsRegisteredNames::class);

/**
 * `resources/`, not the default package root: run from this repository, the root also holds
 * `vendor/`, so Laravel's own `laravel-exceptions`, `notifications` and `pagination` views would be
 * judged as chrono's.
 */
function chronoNamingScope(): NamingScope
{
    return NamingScope::for('laranail/chrono', 'Simtabi\\Laranail\\Chrono\\', basePath: dirname(__DIR__, 2) . '/resources');
}

/**
 * The view and translation namespaces chrono registers, read from the live registries.
 *
 * `laranail/chrono` is canonical for `view()` and `__()`, since it names the composer
 * package; `laranail-chrono` stays, because a Blade tag cannot spell a slash.
 */
it('registers its views under both laranail/chrono and laranail-chrono', function (): void {
    $scope = chronoNamingScope();

    expect($this->assertViewNamespacesScoped($scope, atLeast: 2))
        ->toContain('laranail/chrono', 'laranail-chrono');

    expect(View::exists('laranail/chrono::components.select'))->toBeTrue()
        ->and(View::exists('laranail-chrono::components.select'))->toBeTrue();
});

it('registers its translations under both laranail/chrono and laranail-chrono', function (): void {
    $scope = chronoNamingScope();

    expect($this->assertTranslationNamespacesScoped($scope, atLeast: 2))
        ->toContain('laranail/chrono', 'laranail-chrono');
});

it('resolves the same translation through either namespace', function (): void {
    $key = array_key_first(require dirname(__DIR__, 2) . '/resources/lang/en/messages.php');

    $canonical = __("laranail/chrono::messages.{$key}");

    expect($canonical)->toBe(__("laranail-chrono::messages.{$key}"))
        ->and($canonical)->not->toBe("laranail/chrono::messages.{$key}");
});
