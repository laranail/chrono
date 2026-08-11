<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Models\Concerns;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use NoDiscard;
use Simtabi\Laranail\Chrono\Casts\AsTimezone;
use Simtabi\Laranail\Chrono\Core\Concerns\InteractsWithTimezones;
use Simtabi\Laranail\Chrono\Core\Timezone\Value\Timezone;

/**
 * A model that belongs to a timezone — a user, a venue, a tenant, a store.
 *
 * The column is almost always on a table this package does not own, so there is no migration here.
 * Add a `string('timezone')->nullable()` yourself and `use HasTimezone;` on the model.
 *
 *     final class User extends Authenticatable
 *     {
 *         use HasTimezone;
 *     }
 *
 *     $user->timezone;                          // a Timezone object, not a string
 *     $user->timezone = 'US/Eastern';           // stored as America/New_York
 *     $user->localNow();                        // now, in their zone
 *     $user->localTime($order->placed_at);      // that instant, in their zone
 *     User::query()->whereTimezoneCountry('KE')->get();
 *
 * ## What the trait is actually for
 *
 * Canonicalising on write. The resolver deliberately *accepts* deprecated aliases, because that is
 * what lets `US/Eastern` from a legacy integration resolve at all — but stored verbatim, a column
 * ends up holding `Asia/Calcutta` and `Asia/Kolkata` for the same place, and every `where` and
 * `group by` treats them as two. The cast rewrites before the value reaches the database, so a
 * comparison is a string comparison again.
 *
 * The cast is registered by the trait's initialiser and yields to an explicit declaration, so a
 * model that already casts the column keeps whatever it declared.
 *
 * @phpstan-require-extends Model
 */
trait HasTimezone
{
    use InteractsWithTimezones;

    /**
     * Register the cast, unless the model already declares one for this column.
     *
     * Eloquent calls this from the constructor for every trait named `initialize{Trait}`. Yielding
     * to an existing declaration matters: a model may deliberately cast the column with
     * `AsTimezone::verbatim()` to preserve exactly what a third party submitted.
     */
    public function initializeHasTimezone(): void
    {
        $column = $this->timezoneColumn();

        if (! array_key_exists((string) $column, $this->casts)) {
            $this->mergeCasts([$column => AsTimezone::class]);
        }
    }

    /** Override on the model when the column is not called `timezone`. */
    public function timezoneColumn(): string
    {
        return property_exists($this, 'timezoneColumn') && is_string($this->timezoneColumn)
            ? $this->timezoneColumn
            : 'timezone';
    }

    /** The model's zone, or null when the column is empty or holds something unresolvable. */
    public function timezone(): ?Timezone
    {
        $value = $this->getAttribute($this->timezoneColumn());

        if ($value instanceof Timezone) {
            return $value;
        }

        return is_string($value) && $value !== '' ? $this->tryZone($value) : null;
    }

    /**
     * The model's zone, falling back to the application default.
     *
     * Use this anywhere a null zone would mean rendering in the server's zone by accident — which
     * is how a user in Honolulu is shown a date one day out.
     */
    public function timezoneOrDefault(): Timezone
    {
        return $this->timezone() ?? $this->defaultTimezone();
    }

    public function hasTimezone(): bool
    {
        return $this->timezone() instanceof Timezone;
    }

    /** The current instant, in this model's zone. */
    #[NoDiscard]
    public function localNow(): DateTimeInterface
    {
        return $this->nowInZone($this->timezoneOrDefault());
    }

    /**
     * Re-express an instant in this model's zone. The moment does not change, only how it reads.
     *
     * Passing an attribute name reads that attribute first, so `$user->localTime('created_at')` and
     * `$user->localTime($user->created_at)` are the same call.
     */
    #[NoDiscard]
    public function localTime(DateTimeInterface|string $instant): ?DateTimeInterface
    {
        if (is_string($instant)) {
            $attribute = $this->getAttribute($instant);

            if (! $attribute instanceof DateTimeInterface) {
                return null;
            }

            $instant = $attribute;
        }

        return $this->inZone($instant, $this->timezoneOrDefault());
    }

    /** How far ahead of another zone this model is, in seconds, at an instant. */
    public function timezoneOffsetFrom(mixed $other, ?DateTimeInterface $at = null): int
    {
        return $this->timezoneOrDefault()->diff($this->zone($other), $at)->seconds;
    }

    // ── scopes ──────────────────────────────────────────────────────────────────────────────

    /**
     * Rows in a zone, matched canonically.
     *
     * `whereTimezone('US/Eastern')` finds rows stored as `America/New_York`, which a plain `where`
     * on the column does not.
     *
     * @param Builder<covariant Model> $query
     */
    public function scopeWhereTimezone(Builder $query, mixed $zone): void
    {
        $query->where($this->timezoneColumn(), $this->zoneIdentifier($zone));
    }

    /**
     * @param Builder<covariant Model> $query
     * @param iterable<mixed> $zones anything the resolver accepts
     */
    public function scopeWhereTimezoneIn(Builder $query, iterable $zones): void
    {
        $identifiers = [];

        foreach ($zones as $zone) {
            $identifiers[] = $this->zoneIdentifier($zone);
        }

        $query->whereIn($this->timezoneColumn(), array_values(array_unique($identifiers)));
    }

    /**
     * Rows whose zone belongs to a country — "every store in Kenya", without a country column.
     *
     * @param Builder<covariant Model> $query
     */
    public function scopeWhereTimezoneCountry(Builder $query, string ...$countryCodes): void
    {
        $identifiers = [];

        foreach ($countryCodes as $code) {
            $identifiers = [...$identifiers, ...$this->zonesInCountry($code)->identifiers()];
        }

        $query->whereIn($this->timezoneColumn(), array_values(array_unique($identifiers)));
    }

    /** @param Builder<covariant Model> $query */
    public function scopeWhereTimezoneObservesDst(Builder $query, bool $observes = true): void
    {
        $identifiers = $this->zoneQuery()->observingDst($observes)->identifiers();

        $query->whereIn($this->timezoneColumn(), $identifiers);
    }

    private function defaultTimezone(): Timezone
    {
        $configured = config('laranail.chrono.default');

        return (is_string($configured) ? $this->tryZone($configured) : null)
            ?? $this->timezones()->utc();
    }
}
