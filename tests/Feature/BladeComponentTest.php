<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

/**
 * The component's contract is that it works *before* its JavaScript does, so the assertions are
 * about the markup a browser with scripting disabled would receive.
 */
it('renders a real select with real optgroups', function (): void {
    $html = Blade::render('<x-laranail-chrono::timezone-select name="timezone" />');

    expect($html)->toContain('<select')
        ->toContain('name="timezone"')
        ->toContain('<optgroup label="Africa"')
        ->toContain('value="Africa/Nairobi"');
});

it('marks the current value as selected', function (): void {
    $html = Blade::render('<x-laranail-chrono::timezone-select name="tz" selected="Asia/Tokyo" />');

    expect($html)->toMatch('/value="Asia\/Tokyo"[^>]*selected/');
});

it('carries its configuration in data attributes, not inline script', function (): void {
    $html = Blade::render('<x-laranail-chrono::timezone-select name="tz" />');

    expect($html)->toContain('data-chrono-select')
        ->toContain('data-chrono-select-input')
        ->toContain('data-chrono-searchable="true"')
        ->toContain('data-chrono-search-placeholder=')
        ->toContain('data-chrono-empty=');
});

/** A JSON block rather than an assignment, so a strict CSP needs no 'unsafe-inline'. */
it('embeds its options as inert JSON', function (): void {
    $html = Blade::render('<x-laranail-chrono::timezone-select name="tz" />');

    expect($html)->toContain('<script type="application/json" data-chrono-options>')
        ->and($html)->not->toContain('<script>');

    preg_match('/data-chrono-options>(.*?)<\/script>/s', $html, $matches);
    $payload = json_decode(html_entity_decode($matches[1]), true);

    expect($payload)->toBeArray()
        ->and($payload[0])->toHaveKeys(['id', 'label', 'search', 'dir']);
});

it('groups by whatever it is told to', function (string $group, string $expected): void {
    $html = Blade::render('<x-laranail-chrono::timezone-select name="tz" group="' . $group . '" />');

    expect($html)->toContain($expected);
})->with([
    ['continent', '<optgroup label="Africa"'],
    ['country', '<optgroup label="Kenya"'],
    ['none', '<option value="Africa/Nairobi"'],
]);

it('can be rendered without the search layer', function (): void {
    expect(Blade::render('<x-laranail-chrono::timezone-select name="tz" :searchable="false" />'))
        ->toContain('data-chrono-searchable="false"');
});

it('sets direction from the locale', function (): void {
    expect(Blade::render('<x-laranail-chrono::timezone-select name="tz" locale="ar" />'))->toContain('dir="rtl"')
        ->and(Blade::render('<x-laranail-chrono::timezone-select name="tz" locale="en" />'))->toContain('dir="ltr"');
});

it('honours required and disabled', function (): void {
    $html = Blade::render('<x-laranail-chrono::timezone-select name="tz" :required="true" :disabled="true" />');

    expect($html)->toContain('required')->toContain('disabled');
});

it('publishes the enhancement script', function (): void {
    expect(ServiceProvider::$publishGroups)->toHaveKey('laranail::chrono-assets');
});

it('ships a script with no dependencies and no build step', function (): void {
    $js = file_get_contents(dirname(__DIR__, 2) . '/resources/js/chrono-select.js');

    expect($js)->toContain('data-chrono-select')
        ->toContain('listbox')                // it builds an accessible combobox
        ->toContain('combobox')
        ->toContain('aria-activedescendant')
        ->toContain('aria-expanded')
        ->and($js)->not->toContain('import ')  // no module graph
        ->and($js)->not->toContain('require(');
});
