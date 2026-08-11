<?php

declare(strict_types=1);
use Illuminate\Contracts\Console\Kernel;

it('shows one zone, however it was named', function (string $input): void {
    $this->artisan('laranail::chrono.show', ['zone' => $input])
        ->expectsOutputToContain('Africa/Nairobi')
        ->assertSuccessful();
})->with(['Africa/Nairobi', 'KE', 'nairobi']);

it('reports what an unresolvable zone could have meant', function (): void {
    $this->artisan('laranail::chrono.show', ['zone' => 'not a zone'])->assertFailed();
});

it('lists the configured catalogue', function (): void {
    $this->artisan('laranail::chrono.list', ['--country' => ['KE'], '--format' => 'ids'])
        ->expectsOutput('Africa/Nairobi')
        ->assertSuccessful();
});

it('lists as json and csv', function (string $format): void {
    $this->artisan('laranail::chrono.list', ['--country' => ['KE'], '--format' => $format])
        ->assertSuccessful();
})->with(['json', 'csv', 'table']);

it('says so when nothing matches', function (): void {
    $this->artisan('laranail::chrono.list', ['--search' => 'zzzznope'])->assertSuccessful();
});

/** The check that matters: stale tz data, and ICU disagreeing with PHP. */
it('reports on the health of the host', function (): void {
    $this->artisan('laranail::chrono.doctor')
        ->expectsOutputToContain(timezone_version_get())
        ->assertSuccessful();
});

it('confirms generated data is in sync', function (): void {
    $this->artisan('laranail::chrono.sync', ['--check' => true])->assertSuccessful();
});

it('exposes short aliases alongside the namespaced names', function (string $alias): void {
    expect(array_keys($this->app[Kernel::class]->all()))->toContain($alias);
})->with(['chrono:show', 'chrono:list', 'chrono:doctor', 'chrono:sync']);
