<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use Simtabi\Laranail\Chrono\Core\Timezone\Value\Timezone;
use Simtabi\Laranail\Chrono\Facades\Chrono;
use Simtabi\Laranail\Chrono\Facades\Timezones;
use Simtabi\Laranail\Chrono\Rules\AllowedTimezone;
use Simtabi\Laranail\Chrono\Rules\CanonicalTimezone;
use Simtabi\Laranail\Chrono\Rules\DateTimeExists;
use Simtabi\Laranail\Chrono\Rules\DateTimeUnambiguous;
use Simtabi\Laranail\Chrono\Rules\TimezoneAbbreviation;
use Simtabi\Laranail\Chrono\Rules\TimezoneInCountry;
use Simtabi\Laranail\Chrono\Rules\TimezoneOffset;
use Simtabi\Laranail\Chrono\Tests\Fixtures\TestUser;

beforeEach(function (): void {
    Schema::create('chrono_test_users', function (Blueprint $table): void {
        $table->increments('id');
        $table->string('timezone', 64)->nullable();
    });
});

afterEach(function (): void {
    Schema::dropIfExists('chrono_test_users');
});

describe('the container wiring', function (): void {
    it('resolves both facades', function (): void {
        expect(Timezones::of('Africa/Nairobi'))->toBeInstanceOf(Timezone::class)
            ->and(Chrono::version())->toBe(timezone_version_get())
            ->and(Chrono::timezones())->toBe(app(Simtabi\Laranail\Chrono\Core\Timezone\Timezones::class));
    });

    it('reads the vendor-namespaced config key', function (): void {
        expect(config('laranail.chrono.fallback'))->toBe('UTC');
    });

    it('presents through the root service', function (): void {
        expect(Chrono::present()->query(fn ($q) => $q->inCountry('KE'))->forIdentifiers())
            ->toBe(['Africa/Nairobi']);
    });
});

describe('the Eloquent cast', function (): void {
    it('round-trips a zone', function (): void {
        $user = TestUser::create(['timezone' => 'Africa/Nairobi']);

        expect($user->fresh()->timezone)->toBeInstanceOf(Timezone::class)
            ->and($user->fresh()->timezone->identifier)->toBe('Africa/Nairobi');
    });

    /** The reason the cast exists: an alias must never reach the column. */
    it('canonicalises on write', function (): void {
        $user = TestUser::create(['timezone' => 'Asia/Calcutta']);

        expect($user->fresh()->getRawOriginal('timezone'))->toBe('Asia/Kolkata');
    });

    it('accepts anything the resolver accepts', function (): void {
        expect(TestUser::create(['timezone' => 'KE'])->fresh()->getRawOriginal('timezone'))
            ->toBe('Africa/Nairobi');
    });

    it('accepts a value object', function (): void {
        $user = TestUser::create(['timezone' => Timezones::of('Asia/Tokyo')]);

        expect($user->fresh()->getRawOriginal('timezone'))->toBe('Asia/Tokyo');
    });

    it('handles null', function (): void {
        expect(TestUser::create(['timezone' => null])->fresh()->timezone)->toBeNull();
    });
});

describe('validation rules', function (): void {
    /**
     * Laravel's bare `timezone` rule validates against DateTimeZone::ALL and so rejects aliases
     * already; `timezone:all_with_bc` accepts them silently. This rule accepts them and then
     * insists on the canonical spelling, which is the case neither built-in covers.
     */
    it('sits between the two built-in behaviours', function (): void {
        expect(Validator::make(['tz' => 'US/Eastern'], ['tz' => 'timezone'])->passes())->toBeFalse()
            ->and(Validator::make(['tz' => 'US/Eastern'], ['tz' => 'timezone:all_with_bc'])->passes())->toBeTrue()
            ->and(Validator::make(['tz' => 'US/Eastern'], ['tz' => [new CanonicalTimezone]])->passes())->toBeFalse()
            ->and(Validator::make(['tz' => 'America/New_York'], ['tz' => [new CanonicalTimezone]])->passes())->toBeTrue();
    });

    it('names the canonical form in the message', function (): void {
        $validator = Validator::make(['tz' => 'Asia/Calcutta'], ['tz' => [new CanonicalTimezone]]);

        expect($validator->errors()->first('tz'))->toContain('Asia/Kolkata');
    });

    it('restricts to a supported set', function (): void {
        $rule = new AllowedTimezone(['UTC', 'Africa/Nairobi']);

        expect(Validator::make(['tz' => 'Africa/Nairobi'], ['tz' => [$rule]])->passes())->toBeTrue()
            ->and(Validator::make(['tz' => 'Antarctica/Troll'], ['tz' => [$rule]])->passes())->toBeFalse();
    });

    it('resolves before checking, so an alias of an allowed zone passes', function (): void {
        $rule = new AllowedTimezone(['Asia/Kolkata']);

        expect(Validator::make(['tz' => 'Asia/Calcutta'], ['tz' => [$rule]])->passes())->toBeTrue();
    });

    it('rejects nonsense', function (): void {
        expect(Validator::make(['tz' => 'not a zone'], ['tz' => [new CanonicalTimezone]])->passes())->toBeFalse();
    });
});

describe('publishing', function (): void {
    /**
     * Pinned because the docs quote both the tag and the destination path, and a rename in
     * package-tools would silently make those instructions wrong.
     */
    it('registers the documented tags and destinations', function (): void {
        $groups = ServiceProvider::$publishGroups;

        expect($groups)->toHaveKeys(['laranail::chrono-config', 'laranail::chrono-translations'])
            ->and(implode('', $groups['laranail::chrono-config']))->toContain('config/laranail/chrono.php')
            // `laranail-chrono`, not `laranail/chrono`: package-tools now
            // vendor-scopes the translation namespace by default, because
            // Laravel keeps those in a flat map and a bare one is a collision
            // waiting for a second package. The publish destination moved with
            // it, so a published override belongs under the hyphenated path.
            ->and(implode('', $groups['laranail::chrono-translations']))->toContain('lang/vendor/laranail-chrono');
    });
});

describe('daylight-saving rules', function (): void {
    /**
     * The defect being caught: as a string `2026-03-08 02:30` is perfectly well-formed, so `date`
     * and `date_format` accept it — but that clock reading never happened in New York, and PHP
     * silently resolves it to 03:30 EDT.
     */
    it('rejects a wall-clock reading that never happened', function (): void {
        $data = ['timezone' => 'America/New_York', 'starts_at' => '2026-03-08 02:30'];

        expect(Validator::make($data, ['starts_at' => 'date_format:Y-m-d H:i'])->passes())->toBeTrue()
            ->and(Validator::make($data, ['starts_at' => [new DateTimeExists]])->passes())->toBeFalse();
    });

    it('accepts the same reading in a zone with no such gap', function (): void {
        expect(Validator::make(
            ['timezone' => 'Africa/Nairobi', 'starts_at' => '2026-03-08 02:30'],
            ['starts_at' => [new DateTimeExists]],
        )->passes())->toBeTrue();
    });

    it('rejects a wall-clock reading that happened twice', function (): void {
        expect(Validator::make(
            ['timezone' => 'America/New_York', 'starts_at' => '2026-11-01 01:30'],
            ['starts_at' => [new DateTimeUnambiguous]],
        )->passes())->toBeFalse();
    });

    it('names both instants so the form can offer them', function (): void {
        $validator = Validator::make(
            ['timezone' => 'America/New_York', 'starts_at' => '2026-11-01 01:30'],
            ['starts_at' => [new DateTimeUnambiguous]],
        );

        expect($validator->errors()->first('starts_at'))->toContain('EDT')->toContain('EST');
    });

    it('passes an ordinary time', function (): void {
        $data = ['timezone' => 'America/New_York', 'starts_at' => '2026-06-15 09:00'];

        expect(Validator::make($data, ['starts_at' => [new DateTimeExists]])->passes())->toBeTrue()
            ->and(Validator::make($data, ['starts_at' => [new DateTimeUnambiguous]])->passes())->toBeTrue();
    });

    it('reads the zone from a named field', function (): void {
        expect(Validator::make(
            ['venue_tz' => 'America/New_York', 'starts_at' => '2026-03-08 02:30'],
            ['starts_at' => [new DateTimeExists('venue_tz')]],
        )->passes())->toBeFalse();
    });

    /** The timezone field has its own rule; failing here too would report one mistake twice. */
    it('stays quiet when the zone field is missing or unresolvable', function (): void {
        expect(Validator::make(
            ['timezone' => 'not a zone', 'starts_at' => '2026-03-08 02:30'],
            ['starts_at' => [new DateTimeExists]],
        )->passes())->toBeTrue();
    });
});

describe('the remaining rules', function (): void {
    it('validates a UTC offset in any spelling', function (mixed $value, bool $passes): void {
        expect(Validator::make(['o' => $value], ['o' => [new TimezoneOffset]])->passes())->toBe($passes);
    })->with([
        ['+03:00', true], ['-0530', true], ['+3', true], ['GMT+3', true],
        ['Z', true], [10800, true], ['not an offset', false], ['+99:00', false],
    ]);

    /** Arithmetically valid, but no zone is on it. */
    it('can require the offset to be one a real zone uses', function (): void {
        expect(Validator::make(['o' => '+03:00'], ['o' => [TimezoneOffset::inUse()]])->passes())->toBeTrue()
            ->and(Validator::make(['o' => '+13:37'], ['o' => [TimezoneOffset::inUse()]])->passes())->toBeFalse();
    });

    it('validates an abbreviation against the generated list', function (): void {
        expect(Validator::make(['a' => 'EAT'], ['a' => [new TimezoneAbbreviation]])->passes())->toBeTrue()
            ->and(Validator::make(['a' => 'cst'], ['a' => [new TimezoneAbbreviation]])->passes())->toBeTrue()
            ->and(Validator::make(['a' => 'ZZZ'], ['a' => [new TimezoneAbbreviation]])->passes())->toBeFalse();
    });

    /** The case Laravel's per_country cannot express: the country comes from the request. */
    it('checks a zone against a country chosen in the same form', function (): void {
        $rule = fn (): TimezoneInCountry => new TimezoneInCountry('country');

        expect(Validator::make(['country' => 'KE', 'tz' => 'Africa/Nairobi'], ['tz' => [$rule()]])->passes())->toBeTrue()
            ->and(Validator::make(['country' => 'KE', 'tz' => 'Europe/London'], ['tz' => [$rule()]])->passes())->toBeFalse();
    });

    it('resolves the zone first, so an alias of a matching zone passes', function (): void {
        expect(Validator::make(
            ['country' => 'IN', 'tz' => 'Asia/Calcutta'],
            ['tz' => [new TimezoneInCountry('country')]],
        )->passes())->toBeTrue();
    });

    it('stays quiet when the country field is absent', function (): void {
        expect(Validator::make(['tz' => 'Europe/London'], ['tz' => [new TimezoneInCountry('country')]])->passes())
            ->toBeTrue();
    });
});

describe('the configured catalogue', function (): void {
    /**
     * This was the audit's biggest finding: the whole `catalogue.*` section was documented as
     * controlling which zones the application offers, and nothing read it. `AllowedTimezone` claimed
     * to use "the configured catalogue" while validating against all 419 — so the picker and the
     * validator could disagree, which is precisely the bug that rule is meant to prevent.
     */
    it('restricts every consumer, not just the picker', function (): void {
        config()->set('laranail.chrono.catalogue.only', ['UTC', 'Africa/Nairobi']);

        // The service is a singleton built at registration, so it must be rebuilt for the
        // new config to reach it — the same thing a real application gets on its next boot.
        $this->app->forgetInstance(Simtabi\Laranail\Chrono\Core\Timezone\Timezones::class);

        $timezones = app(Simtabi\Laranail\Chrono\Core\Timezone\Timezones::class);

        expect($timezones->query()->identifiers())->toEqualCanonicalizing(['UTC', 'Africa/Nairobi'])
            ->and($timezones->unrestrictedQuery()->count())->toBeGreaterThan(400);
    });

    it('leaves resolution alone, so an unlisted zone still resolves', function (): void {
        // Restricting what is *offered* is not the same as refusing to understand a value that
        // already exists in a database.
        expect(Timezones::of('Antarctica/Troll')->identifier)->toBe('Antarctica/Troll');
    });
});
