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
})->skip(
    fn (): bool => ! tzdataIsVersioned(),
    'the host reads the OS tz database, which carries no release to compare against',
);

// Inverted deliberately. This used to assert the short aliases existed; they are
// gone, because Artisan keeps command names in a flat global map and `chrono:sync`
// is a plausible claim for anything dealing with time. A second package claiming it
// does not collide loudly -- it silently replaces this one. The guard now proves the
// namespaced name is registered and the bare one is not, so re-adding an alias fails
// here rather than in someone else's application.
it('registers the namespaced name and no bare alias', function (string $command): void {
    $registered = array_keys($this->app[Kernel::class]->all());

    expect($registered)->toContain("laranail::chrono.{$command}")
        ->and($registered)->not->toContain("chrono:{$command}");
})->with(['show', 'list', 'doctor', 'sync']);
