<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Simtabi\Laranail\Chrono\Core\Config\DstPolicy;
use Simtabi\Laranail\Chrono\Models\Concerns\HasTimezone;
use Simtabi\Laranail\Chrono\Core\Config\CatalogueOptions;
use Simtabi\Laranail\Chrono\Core\Support\ServiceResolver;
use Simtabi\Laranail\Chrono\Core\Timezone\Value\Timezone;
use Simtabi\Laranail\Chrono\Tests\Fixtures\TimezonedUser;
use Simtabi\Laranail\Chrono\Core\Exception\SkippedLocalTime;
use Simtabi\Laranail\Chrono\Tests\Fixtures\Core\ChronoConsumer;
use Simtabi\Laranail\Chrono\Core\Enums\Timezone as TimezoneEnum;
use Simtabi\Laranail\Chrono\Core\Timezone\Timezones as TimezonesService;

beforeEach(function (): void {
    Schema::create('chrono_test_users', function (Blueprint $table): void {
        $table->increments('id');
        $table->string('timezone', 64)->nullable();
    });
});

afterEach(function (): void {
    Schema::dropIfExists('chrono_test_users');
});

/**
 * The other half of the traits' claim. `ConcernsTest` proves they work with no container at all;
 * these prove that inside an application they return the *configured* services — which is the part
 * that actually matters, because a helper quietly building its own `new Timezones` would opt out of
 * the application's daylight-saving policy and catalogue and disagree with everything around it.
 */
describe('the same class, inside an application', function (): void {
    it('resolves the container service rather than building its own', function (): void {
        expect(ServiceResolver::isBound())->toBeTrue()
            ->and(ServiceResolver::resolve(TimezonesService::class))
            ->toBe(app(TimezonesService::class));
    });

    it('inherits the configured catalogue', function (): void {
        config()->set('laranail.chrono.catalogue.only', ['UTC', 'Africa/Nairobi']);
        app()->forgetInstance(TimezonesService::class);

        expect((new ChronoConsumer)->offeredZones())->toEqualCanonicalizing(['UTC', 'Africa/Nairobi']);
    });

    /** The setting the package exists for, reaching a class that never mentioned it. */
    it('inherits the configured daylight-saving policy', function (): void {
        config()->set('laranail.chrono.dst.on_gap', 'throw');
        app()->forgetInstance(TimezonesService::class);
        app()->forgetInstance(DstPolicy::class);

        expect(fn (): DateTimeImmutable => (new ChronoConsumer)->bookingInstant('2026-03-08 02:30', 'America/New_York'))
            ->toThrow(SkippedLocalTime::class);
    });

    it('sees the frozen clock this suite installs', function (): void {
        // TestCase freezes at 2026-06-15T12:00:00Z, and Nairobi is UTC+03:00 year round.
        expect((new ChronoConsumer)->currentTimeIn('Africa/Nairobi'))->toBe('15:00');
    });
});

describe('HasTimezone', function (): void {
    it('needs nothing on the model but the trait', function (): void {
        $user = TimezonedUser::query()->create(['timezone' => 'Africa/Nairobi']);

        expect($user->timezone)->toBeInstanceOf(Timezone::class)
            ->and($user->timezone?->identifier)->toBe('Africa/Nairobi')
            ->and($user->timezone())->toBeInstanceOf(Timezone::class);
    });

    /**
     * The reason the trait exists. Stored verbatim, one place accumulates two spellings and every
     * `where` and `group by` treats them as different zones.
     */
    it('canonicalises on write, so a column holds one spelling per place', function (): void {
        TimezonedUser::query()->create(['timezone' => 'US/Eastern']);
        TimezonedUser::query()->create(['timezone' => 'America/New_York']);

        // Read past the cast: the claim is about what is in the column, not what comes back out.
        expect(DB::table('chrono_test_users')->pluck('timezone')->unique()->values()->all())
            ->toBe(['America/New_York']);
    });

    it('accepts anything the resolver does', function (): void {
        $user = TimezonedUser::query()->create(['timezone' => TimezoneEnum::AsiaTokyo]);

        expect($user->refresh()->timezone?->identifier)->toBe('Asia/Tokyo');
    });

    it('reads a row whose column is empty as no zone at all', function (): void {
        $user = TimezonedUser::query()->create(['timezone' => null]);

        expect($user->timezone())->toBeNull()
            ->and($user->hasTimezone())->toBeFalse()
            // …but never renders in the server's zone by accident.
            ->and($user->timezoneOrDefault()->identifier)->toBe(config('laranail.chrono.default'));
    });

    it('expresses an instant in the model zone without moving the moment', function (): void {
        $user = TimezonedUser::query()->create(['timezone' => 'Asia/Tokyo']);
        $instant = new DateTimeImmutable('2026-06-15T12:00:00Z');

        $local = $user->localTime($instant);

        expect($local?->format('H:i'))->toBe('21:00')
            ->and($local?->getTimestamp())->toBe($instant->getTimestamp())
            ->and($user->localNow()->format('H:i'))->toBe('21:00');
    });

    it('reports how far ahead of another zone it is', function (): void {
        $user = TimezonedUser::query()->create(['timezone' => 'Africa/Nairobi']);

        // June: New York is on EDT, UTC-04:00. Nairobi is UTC+03:00 all year.
        expect($user->timezoneOffsetFrom('America/New_York'))->toBe(7 * 3600);
    });

    describe('scopes', function (): void {
        beforeEach(function (): void {
            foreach (['Africa/Nairobi', 'America/New_York', 'Asia/Tokyo', 'Europe/London'] as $zone) {
                TimezonedUser::query()->create(['timezone' => $zone]);
            }
        });

        it('matches canonically, which a plain where does not', function (): void {
            expect(TimezonedUser::query()->whereTimezone('US/Eastern')->count())->toBe(1)
                ->and(TimezonedUser::query()->where('timezone', 'US/Eastern')->count())->toBe(0);
        });

        it('matches a list', function (): void {
            expect(TimezonedUser::query()->whereTimezoneIn(['Asia/Tokyo', 'US/Eastern'])->count())->toBe(2);
        });

        it('finds rows by country without a country column', function (): void {
            expect(TimezonedUser::query()->whereTimezoneCountry('KE')->count())->toBe(1)
                ->and(TimezonedUser::query()->whereTimezoneCountry('KE', 'JP')->count())->toBe(2);
        });

        it('finds rows by whether their zone observes daylight saving', function (): void {
            // Nairobi and Tokyo do not; New York and London do.
            expect(TimezonedUser::query()->whereTimezoneObservesDst(false)->count())->toBe(2)
                ->and(TimezonedUser::query()->whereTimezoneObservesDst()->count())->toBe(2);
        });
    });

    it('yields to a cast the model already declared', function (): void {
        $model = new class extends Model
        {
            use HasTimezone;

            protected $table = 'chrono_test_users';

            protected $casts = ['timezone' => 'string'];
        };

        expect($model->getCasts()['timezone'])->toBe('string');
    });

    it('follows a renamed column', function (): void {
        $model = new class extends Model
        {
            use HasTimezone;

            protected $table = 'chrono_test_users';

            protected string $timezoneColumn = 'tz';
        };

        expect($model->timezoneColumn())->toBe('tz')
            ->and($model->getCasts())->toHaveKey('tz');
    });
});

describe('an explicit service still wins inside an application', function (): void {
    it('prefers what it was handed over what the container holds', function (): void {
        $restricted = app(TimezonesService::class)
            ->withCatalogue(new CatalogueOptions(only: ['Antarctica/Troll']));

        expect((new ChronoConsumer)->withTimezones($restricted)->offeredZones())
            ->toBe(['Antarctica/Troll'])
            ->and((new ChronoConsumer)->offeredZones())->toHaveCount(419);
    });
});
