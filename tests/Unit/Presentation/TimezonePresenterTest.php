<?php

declare(strict_types=1);

use Simtabi\Laranail\Chrono\Core\Enums\ZoneField;
use Simtabi\Laranail\Chrono\Core\Enums\OffsetFormat;
use Simtabi\Laranail\Chrono\Core\Timezone\Timezones;
use Simtabi\Laranail\Chrono\Core\Enums\PresentationPreset;
use Simtabi\Laranail\Chrono\Core\Presentation\PresentedZone;
use Simtabi\Laranail\Chrono\Core\Presentation\TimezonePresenter;

beforeEach(function (): void {
    $this->presenter = new TimezonePresenter(
        (new Timezones)->query()->inCountry('KE', 'GB', 'JP'),
    );
});

describe('grouping', function (): void {
    it('buckets into optgroups by continent', function (): void {
        $groups = $this->presenter->groupByContinent()->forSelect();

        expect($groups)->toHaveKeys(['Africa', 'Asia', 'Europe'])
            ->and($groups['Africa'])->toHaveKey('Africa/Nairobi');
    });

    it('buckets by country, using the localised name', function (): void {
        expect($this->presenter->groupByCountry()->forSelect())->toHaveKeys(['Japan', 'Kenya', 'United Kingdom']);
    });

    /** The +/- view: everything on the same clock together, regardless of geography. */
    it('buckets by offset', function (): void {
        $groups = $this->presenter->groupByOffset()->offsetFormat(OffsetFormat::Colon)->forSelect();

        expect(array_keys($groups))->each->toMatch('/^[+-]\d{2}:\d{2}$/');
    });

    it('can stay flat', function (): void {
        expect($this->presenter->flat()->forSelect())->toHaveKey('Africa/Nairobi');
    });
});

describe('labels', function (): void {
    it('uses a template with placeholders', function (): void {
        $flat = $this->presenter->flat();

        expect($flat->forSelect()['Africa/Nairobi'])->toBe('Nairobi (UTC +03:00)')
            ->and($flat->label('{city}, {country_name} — {abbr}')->forSelect()['Africa/Nairobi'])
            ->toBe('Nairobi, Kenya — EAT');
    });

    it('respects the offset format', function (): void {
        expect($this->presenter->flat()->label('{gmt}')->offsetFormat(OffsetFormat::Short)->forSelect()['Africa/Nairobi'])
            ->toBe('+3');
    });

    /** Computed from the ISO code, not a lookup table, so every country works. */
    it('derives a flag emoji', function (): void {
        expect($this->presenter->flat()->label('{flag}')->forSelect()['Africa/Nairobi'])->toBe('🇰🇪');
    });
});

describe('field selection', function (): void {
    it('grows the payload with the preset', function (PresentationPreset $preset, int $expected): void {
        $row = $this->presenter->preset($preset)->flat()->forApi()[0];

        expect($row)->toHaveCount($expected);
    })->with([
        [PresentationPreset::Minimal, 1],
        [PresentationPreset::Select, 2],
        [PresentationPreset::Form, 8],
        [PresentationPreset::Api, 12],
    ]);

    it('adds and removes individual fields', function (): void {
        $row = $this->presenter->preset(PresentationPreset::Select)
            ->with(ZoneField::Abbreviation)
            ->without(ZoneField::Label)
            ->flat()
            ->forApi()[0];

        expect($row)->toHaveKeys(['id', 'abbreviation'])
            ->and($row)->not->toHaveKey('label');
    });

    it('replaces the set entirely with only()', function (): void {
        $rows = $this->presenter->only(ZoneField::Id)->flat()->forApi();

        expect($rows)->each->toHaveCount(1)
            ->and(array_column($rows, 'id'))->toContain('Africa/Nairobi');
    });
});

describe('output shapes', function (): void {
    it('renders each one', function (): void {
        $presenter = $this->presenter->preset(PresentationPreset::Api)->flat();

        expect($presenter->forSelect())->toBeArray()
            ->and($presenter->forApi())->toBeArray()
            ->and($presenter->forObjects()[0])->toBeInstanceOf(PresentedZone::class)
            ->and($presenter->forIdentifiers())->toContain('Africa/Nairobi')
            ->and($presenter->forJson())->toBeString()
            ->and(json_decode($presenter->forJson(), true))->toBeArray();
    });

    it('gives a form component value/label/group/dir', function (): void {
        $option = $this->presenter->groupByContinent()->forFormComponent()[0];

        expect($option)->toHaveKeys(['value', 'label', 'group', 'dir']);
    });

    it('returns objects that serialise to the same array', function (): void {
        $presenter = $this->presenter->preset(PresentationPreset::Select)->flat();

        expect($presenter->forObjects()[0]->toArray())->toBe($presenter->forApi()[0]);
    });
});

it('marks direction for a right-to-left locale', function (): void {
    expect($this->presenter->locale('ar')->preset(PresentationPreset::Form)->flat()->forApi()[0]['dir'])->toBe('rtl')
        ->and($this->presenter->locale('en')->preset(PresentationPreset::Form)->flat()->forApi()[0]['dir'])->toBe('ltr');
});

it('localises country names', function (): void {
    $named = fn (string $locale): array => array_column(
        $this->presenter->locale($locale)->with(ZoneField::CountryName)->flat()->forApi(),
        'country_name',
        'id',
    );

    $english = $named('en');
    $swahili = $named('sw');

    // Asserting the property rather than the string, because ICU's wording varies by build.
    expect($english['Africa/Nairobi'])->toBe('Kenya')
        ->and($swahili['Europe/London'])->not->toBe($english['Europe/London'])
        ->and($swahili['Europe/London'])->not->toBe('GB');
});

it('never mutates the presenter it was called on', function (): void {
    $grouped = $this->presenter->groupByContinent();
    $flat = $grouped->flat();

    expect($grouped->forSelect())->toHaveKey('Africa')
        ->and($flat->forSelect())->toHaveKey('Africa/Nairobi');
});

it('composes onto the underlying query', function (): void {
    $narrowed = new TimezonePresenter((new Timezones)->query()->inCountry('KE'));

    expect($narrowed->query(fn ($q) => $q->inCountry('KE'))->count())->toBe(1);
});
