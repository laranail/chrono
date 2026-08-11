<?php

declare(strict_types=1);

use Simtabi\Laranail\Chrono\Core\Timezone\Repository\PhpTimezoneRepository;
use Simtabi\Laranail\Chrono\Core\Timezone\Resolver\ResolutionContext;
use Simtabi\Laranail\Chrono\Core\Timezone\Resolver\ResolverChain;
use Simtabi\Laranail\Chrono\Core\Timezone\Value\Timezone;

beforeEach(function (): void {
    $this->repository = new PhpTimezoneRepository;
    $this->chain = ResolverChain::default();
    $this->context = new ResolutionContext($this->repository);
});

it('interprets whatever an application is handed', function (mixed $input, string $expected): void {
    expect($this->chain->resolve($input, $this->context)?->identifier)->toBe($expected);
})->with('resolvable inputs');

it('accepts objects that already carry a zone', function (): void {
    expect($this->chain->resolve(zone('Europe/Berlin'), $this->context)?->identifier)->toBe('Europe/Berlin')
        ->and($this->chain->resolve(new Timezone('Asia/Tokyo'), $this->context)?->identifier)->toBe('Asia/Tokyo')
        ->and($this->chain->resolve(utc('2026-06-15 12:00'), $this->context)?->identifier)->toBe('UTC');
});

/**
 * Order is load-bearing. `identifier` searches canonical names first and only then the
 * self-canonical set — `EST`, `CET`, `Etc/*` — so a genuine alias falls through to `alias` instead
 * of being handed back unchanged. Getting this backwards is exactly how `Asia/Calcutta` and
 * `Asia/Kolkata` end up in a database as two different zones.
 */
it('canonicalises an alias rather than echoing it', function (string $alias, string $canonical): void {
    expect($this->chain->resolve($alias, $this->context)?->identifier)->toBe($canonical);
})->with('aliases');

it('still resolves identifiers that stand for themselves', function (): void {
    $resolution = $this->chain->resolve('EST', new ResolutionContext($this->repository, allowAbbreviations: true));

    expect($resolution?->identifier)->toBe('EST')
        ->and($resolution?->via)->toBe('identifier');
});

it('returns nothing for input it does not recognise', function (): void {
    expect($this->chain->resolve('definitely not a zone', $this->context))->toBeNull();
});

describe('ambiguity', function (): void {
    it('refuses to pick a zone for a multi-zone country in strict mode', function (): void {
        // The United States has 29 zones. Choosing one for a user is a bug waiting to be filed.
        expect($this->chain->resolve('US', $this->context))->toBeNull();
    });

    it('offers a low-confidence answer with alternatives when lenient', function (): void {
        $resolution = $this->chain->resolve('US', new ResolutionContext($this->repository, strict: false));

        expect($resolution)->not->toBeNull()
            ->and($resolution->confidence)->toBeLessThan(0.5)
            ->and(count($resolution->alternatives))->toBeGreaterThan(1);
    });

    it('leaves abbreviations alone unless asked', function (): void {
        // 96 of the 144 abbreviations PHP knows map to more than one zone; CST matches 62.
        expect($this->chain->resolve('EAT', $this->context))->toBeNull();
    });

    it('uses a country bias to settle an ambiguous abbreviation', function (): void {
        $context = new ResolutionContext($this->repository, preferredCountries: ['US'], allowAbbreviations: true);
        $resolution = $this->chain->resolve('CST', $context);

        expect($resolution?->identifier)->toBe('America/Chicago')
            ->and(count($resolution->alternatives))->toBeGreaterThan(10);
    });
});

it('can be narrowed to a configured subset', function (): void {
    $narrowed = $this->chain->only('identifier', 'alias');

    expect($this->chain->keys())->toHaveCount(9)
        ->and($narrowed->keys())->toHaveCount(2)
        ->and($narrowed->resolve('Nairobi', $this->context))->toBeNull();
});
