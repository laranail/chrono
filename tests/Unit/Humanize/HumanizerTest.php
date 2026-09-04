<?php

declare(strict_types=1);

use Simtabi\Laranail\Chrono\Core\Enums\TimeUnit;
use Simtabi\Laranail\Chrono\Core\Humanize\Humanizer;
use Simtabi\Laranail\Chrono\Core\Testing\FrozenClock;
use Simtabi\Laranail\Chrono\Core\Humanize\MessageCatalogue;

beforeEach(function (): void {
    $this->humanizer = (new Humanizer)->withClock(new FrozenClock('2026-06-15T12:00:00Z'));
});

it('phrases a difference in the past and the future', function (string $instant, string $expected): void {
    expect($this->humanizer->diffForHumans(utc($instant)))->toBe($expected);
})->with([
    '3 hours ago' => ['2026-06-15 09:00', '3 hours ago'],
    'in 2 days'   => ['2026-06-17 12:00', 'in 2 days'],
    'just now'    => ['2026-06-15 11:59:50', 'just now'],
    'singular'    => ['2026-06-14 12:00', '1 day ago'],
]);

it('can include more than one unit', function (): void {
    expect($this->humanizer->diffForHumans(utc('2026-06-15 10:35'), parts: 2))->toBe('1 hour 25 minutes ago');
});

/**
 * Differences are measured from timestamps, never `DateTime::diff()`. Diffing 2026-03-08 00:00 to
 * 2026-03-09 00:00 in New York reports "1d 0h" for a span that is really 23 hours, because diff()
 * reports wall-clock difference rather than elapsed time.
 */
it('measures elapsed time, not wall-clock difference', function (): void {
    $newYork = zone('America/New_York');
    $start = new DateTimeImmutable('2026-03-08 00:00', $newYork);
    $end = new DateTimeImmutable('2026-03-09 00:00', $newYork);

    $interval = $start->diff($end);

    // diff() says a day; the clock actually advanced 23 hours.
    expect($interval->d)->toBe(1)
        ->and($interval->h)->toBe(0)
        ->and($end->getTimestamp() - $start->getTimestamp())->toBe(23 * 3600);

    // The humanizer reads the elapsed seconds. It still says "1 day", because 22 hours and up
    // promote to a day by convention — but it got there from 82800 seconds, not from a field
    // that had already rounded. Ask below the threshold and the difference is visible.
    expect($this->humanizer->duration($end->getTimestamp() - $start->getTimestamp()))->toBe('1 day')
        ->and($this->humanizer->duration(21 * 3600))->toBe('21 hours');
});

/**
 * The reason this is built on MessageFormatter rather than trans_choice. Arabic has six plural
 * categories and Laravel's `singular|plural` pipe syntax has two, so the pipe form cannot express
 * "one day", "two days" and "five days" as the three distinct forms Arabic actually uses.
 */
describe('plural rules', function (): void {
    it('renders Arabic through its distinct categories', function (): void {
        expect($this->humanizer->duration(86400, 'ar'))->toBe('يوم واحد')
            ->and($this->humanizer->duration(2 * 86400, 'ar'))->toBe('يومان')
            ->and($this->humanizer->duration(86400, 'ar'))->not->toBe($this->humanizer->duration(2 * 86400, 'ar'));
    });

    it('pluralises Swahili nouns that change class', function (): void {
        // mwezi -> miezi
        expect($this->humanizer->duration(2629746, 'sw'))->not->toBe($this->humanizer->duration(3 * 2629746, 'sw'));
    });

    it('frames past and future per locale', function (): void {
        expect($this->humanizer->diffForHumans(utc('2026-06-15 09:00'), locale: 'fr'))->toContain('il y a')
            ->and($this->humanizer->diffForHumans(utc('2026-06-15 09:00'), locale: 'ar'))->toContain('منذ')
            ->and($this->humanizer->diffForHumans(utc('2026-06-15 09:00'), locale: 'sw'))->toContain('tangu');
    });
});

/**
 * ICU picks a plural branch from the locale tag it is handed. Falling back to an English *pattern*
 * while still formatting under an unknown tag selects "other" and produces "1 days" — so the
 * catalogue reports which locale a pattern came from, and formatting uses that.
 */
it('formats a fallback pattern under the locale that owns it', function (): void {
    expect($this->humanizer->duration(86400, 'xx_YY'))->toBe('1 day')
        ->and($this->humanizer->duration(86400, 'sw_KE'))->toContain('siku');
});

it('accepts application-supplied patterns', function (): void {
    $catalogue = (new MessageCatalogue)->with('en', ['day' => '{n, plural, one {# sleep} other {# sleeps}}']);

    expect($this->humanizer->withCatalogue($catalogue)->duration(2 * 86400, 'en'))->toBe('2 sleeps');
});

it('promotes to a larger unit at the conventional thresholds', function (int $seconds, TimeUnit $expected): void {
    expect($this->humanizer->unitFor($seconds))->toBe($expected);
})->with([
    '44 seconds stays seconds'    => [44, TimeUnit::Second],
    '45 seconds becomes a minute' => [45, TimeUnit::Minute],
    '23 hours becomes a day'      => [82800, TimeUnit::Day],
    '40 days becomes a month'     => [40 * 86400, TimeUnit::Month],
]);
