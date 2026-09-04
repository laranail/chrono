<?php

declare(strict_types=1);

use Simtabi\Laranail\Chrono\Core\Period\Period;
use Simtabi\Laranail\Chrono\Core\Period\Precision;
use Simtabi\Laranail\Chrono\Core\Period\Boundaries;
use Simtabi\Laranail\Chrono\Core\Period\Visualizer;
use Simtabi\Laranail\Chrono\Core\Exception\InvalidPeriod;
use Simtabi\Laranail\Chrono\Core\Period\PeriodCollection;

function period(string $start, string $end, Precision $precision = Precision::Day): Period
{
    return Period::make($start, $end, $precision);
}

describe('construction', function (): void {
    it('rounds both ends to its precision', function (): void {
        $period = Period::make('2026-03-14 09:31:07', '2026-03-16 22:05:59', Precision::Day);

        expect($period->includedStart()->format('Y-m-d H:i:s'))->toBe('2026-03-14 00:00:00')
            ->and($period->includedEnd()->format('Y-m-d H:i:s'))->toBe('2026-03-16 00:00:00');
    });

    it('refuses to end before it starts', function (): void {
        expect(fn (): Period => Period::make('2026-03-16', '2026-03-14'))
            ->toThrow(InvalidPeriod::class, 'cannot end before it starts');
    });

    it('reads anything date-like', function (): void {
        expect(Period::make(new DateTimeImmutable('2026-01-01'), '2026-01-31')->length())->toBe(31);
    });

    it('says so when a string is not a date', function (): void {
        expect(fn (): Period => Period::make('not a date', '2026-01-01'))->toThrow(InvalidPeriod::class);
    });
});

describe('boundaries', function (): void {
    it('includes both ends by default', function (): void {
        $period = period('2026-01-01', '2026-01-03');

        expect($period->length())->toBe(3)
            ->and($period->isStartIncluded())->toBeTrue()
            ->and($period->isEndIncluded())->toBeTrue();
    });

    it('drops the excluded end from the span', function (): void {
        $period = Period::make('2026-01-01', '2026-01-03', Precision::Day, Boundaries::ExcludeEnd);

        expect($period->length())->toBe(2)
            ->and($period->includedEnd()->format('Y-m-d'))->toBe('2026-01-02');
    });

    it('decides whether two meetings collide or merely meet', function (): void {
        // The whole point of boundaries: same two spans, different answer.
        $morning = Period::make('2026-01-01 09:00', '2026-01-01 10:00', Precision::Hour);
        $later = Period::make('2026-01-01 10:00', '2026-01-01 11:00', Precision::Hour);

        expect($morning->overlapsWith($later))->toBeTrue();

        $exclusive = Period::make('2026-01-01 09:00', '2026-01-01 10:00', Precision::Hour, Boundaries::ExcludeEnd);

        expect($exclusive->overlapsWith($later))->toBeFalse()
            ->and($exclusive->touchesWith($later))->toBeTrue();
    });
});

describe('comparisons', function (): void {
    it('answers where it starts and ends relative to a moment', function (): void {
        $period = period('2026-01-10', '2026-01-20');
        $before = new DateTimeImmutable('2026-01-05');
        $after = new DateTimeImmutable('2026-01-25');

        expect($period->startsAfter($before))->toBeTrue()
            ->and($period->startsBefore($after))->toBeTrue()
            ->and($period->startsAt(new DateTimeImmutable('2026-01-10')))->toBeTrue()
            ->and($period->endsAt(new DateTimeImmutable('2026-01-20')))->toBeTrue()
            ->and($period->endsBeforeOrAt($after))->toBeTrue()
            ->and($period->endsAfterOrAt($before))->toBeTrue();
    });

    it('contains a moment and a smaller period', function (): void {
        $period = period('2026-01-01', '2026-01-31');

        expect($period->contains(new DateTimeImmutable('2026-01-15')))->toBeTrue()
            ->and($period->contains(period('2026-01-10', '2026-01-20')))->toBeTrue()
            ->and($period->contains(period('2026-01-20', '2026-02-10')))->toBeFalse();
    });

    it('is equal to another with the same span and precision', function (): void {
        expect(period('2026-01-01', '2026-01-31')->equals(period('2026-01-01', '2026-01-31')))->toBeTrue();
    });

    it('refuses to compare periods measuring different units', function (): void {
        // Rounding one to the other would decide the answer, so it is not done.
        expect(fn (): bool => period('2026-01-01', '2026-01-31', Precision::Day)
            ->overlapsWith(period('2026-01-01', '2026-01-31', Precision::Month)))
            ->toThrow(InvalidPeriod::class, 'cannot be compared');
    });
});

describe('operations', function (): void {
    it('finds the span shared by several periods', function (): void {
        $overlap = period('2026-01-01', '2026-01-31')
            ->overlap(period('2026-01-10', '2026-02-10'), period('2026-01-15', '2026-01-20'));

        expect((string) $overlap)->toBe('[2026-01-15, 2026-01-20]');
    });

    it('returns nothing when they do not all overlap', function (): void {
        expect(period('2026-01-01', '2026-01-05')->overlap(period('2026-02-01', '2026-02-05')))->toBeNull();
    });

    it('collects each overlap separately', function (): void {
        $overlaps = period('2026-01-01', '2026-01-31')->overlapAny(
            period('2026-01-05', '2026-01-10'),
            period('2026-01-20', '2026-01-25'),
            period('2026-03-01', '2026-03-05'),
        );

        expect($overlaps)->toHaveCount(2);
    });

    it('subtracts, leaving what is left on either side', function (): void {
        $remaining = period('2026-01-01', '2026-01-31')->subtract(period('2026-01-10', '2026-01-20'));

        expect($remaining)->toHaveCount(2)
            ->and((string) $remaining->first())->toBe('[2026-01-01, 2026-01-09]')
            ->and((string) $remaining->last())->toBe('[2026-01-21, 2026-01-31]');
    });

    it('subtracts a period that covers everything, leaving nothing', function (): void {
        expect(period('2026-01-10', '2026-01-20')->subtract(period('2026-01-01', '2026-01-31')))->toBeEmpty();
    });

    it('finds the gap between two periods', function (): void {
        expect((string) period('2026-01-01', '2026-01-10')->gap(period('2026-01-20', '2026-01-31')))
            ->toBe('[2026-01-11, 2026-01-19]');
    });

    it('has no gap when they overlap or touch', function (): void {
        expect(period('2026-01-01', '2026-01-15')->gap(period('2026-01-10', '2026-01-31')))->toBeNull()
            ->and(period('2026-01-01', '2026-01-10')->gap(period('2026-01-11', '2026-01-31')))->toBeNull();
    });

    it('takes the symmetric difference', function (): void {
        $diff = period('2026-01-01', '2026-01-20')->diffSymmetric(period('2026-01-10', '2026-01-31'));

        expect($diff)->toHaveCount(2)
            ->and((string) $diff->first())->toBe('[2026-01-01, 2026-01-09]')
            ->and((string) $diff->last())->toBe('[2026-01-21, 2026-01-31]');
    });

    it('renews for the same length, starting where it ended', function (): void {
        $renewed = period('2026-01-01', '2026-01-31')->renew();

        expect($renewed->includedStart()->format('Y-m-d'))->toBe('2026-02-01')
            ->and($renewed->length())->toBe(period('2026-01-01', '2026-01-31')->length());
    });
});

describe('measurement', function (): void {
    it('counts whole steps of its own precision', function (): void {
        expect(period('2026-01-01', '2026-01-31')->length())->toBe(31)
            ->and(period('2026-01-01', '2026-12-01', Precision::Month)->length())->toBe(12);
    });

    it('describes its duration', function (): void {
        $duration = period('2026-01-01', '2026-01-31')->duration();

        expect($duration->inSteps())->toBe(31)
            ->and((string) $duration)->toBe('31 days');
    });

    it('compares durations within one precision', function (): void {
        $week = period('2026-01-01', '2026-01-07')->duration();
        $month = period('2026-01-01', '2026-01-31')->duration();

        expect($month->isLongerThan($week))->toBeTrue()
            ->and($week->isShorterThan($month))->toBeTrue()
            ->and($week->equals(period('2026-02-01', '2026-02-07')->duration()))->toBeTrue();
    });

    it('lists every moment it covers', function (): void {
        expect(period('2026-01-01', '2026-01-03')->moments())->toHaveCount(3);
    });

    it('gives the first moment after itself', function (): void {
        expect(period('2026-01-01', '2026-01-31')->ceilingEnd()->format('Y-m-d'))->toBe('2026-02-01');
    });
});

describe('collections', function (): void {
    it('merges overlapping and touching spans', function (): void {
        $union = new PeriodCollection(
            period('2026-01-01', '2026-01-10'),
            period('2026-01-05', '2026-01-20'),
            period('2026-01-21', '2026-01-25'),
            period('2026-03-01', '2026-03-05'),
        )->union();

        expect($union)->toHaveCount(2)
            ->and((string) $union->first())->toBe('[2026-01-01, 2026-01-25]');
    });

    it('finds the holes between what it holds', function (): void {
        $gaps = new PeriodCollection(
            period('2026-01-01', '2026-01-10'),
            period('2026-01-20', '2026-01-31'),
        )->gaps();

        expect($gaps)->toHaveCount(1)
            ->and((string) $gaps->first())->toBe('[2026-01-11, 2026-01-19]');
    });

    it('spans everything it holds, gaps included', function (): void {
        expect((string) new PeriodCollection(
            period('2026-01-01', '2026-01-10'),
            period('2026-03-01', '2026-03-05'),
        )->boundaries())->toBe('[2026-01-01, 2026-03-05]');
    });

    it('finds when everyone is free', function (): void {
        // The question the whole collection exists for.
        $alice = new PeriodCollection(period('2026-01-01', '2026-01-10'), period('2026-01-20', '2026-01-31'));
        $bob = new PeriodCollection(period('2026-01-05', '2026-01-25'));

        $both = $alice->overlapAll($bob);

        expect($both)->toHaveCount(2)
            ->and((string) $both->first())->toBe('[2026-01-05, 2026-01-10]')
            ->and((string) $both->last())->toBe('[2026-01-20, 2026-01-25]');
    });

    it('subtracts whole collections', function (): void {
        $remaining = new PeriodCollection(period('2026-01-01', '2026-01-31'))
            ->subtract(new PeriodCollection(period('2026-01-10', '2026-01-20')));

        expect($remaining)->toHaveCount(2);
    });

    it('clips everything to a window', function (): void {
        $clipped = new PeriodCollection(
            period('2026-01-01', '2026-01-31'),
            period('2026-02-01', '2026-02-28'),
        )->intersect(period('2026-01-15', '2026-02-15'));

        expect($clipped)->toHaveCount(2)
            ->and((string) $clipped->first())->toBe('[2026-01-15, 2026-01-31]');
    });

    it('maps, filters and reduces', function (): void {
        $collection = new PeriodCollection(
            period('2026-01-01', '2026-01-10'),
            period('2026-02-01', '2026-02-28'),
        );

        expect($collection->filter(fn (Period $p): bool => $p->length() > 20))->toHaveCount(1)
            ->and($collection->reduce(fn (?int $carry, Period $p): int => (int) $carry + $p->length(), 0))->toBe(38)
            ->and($collection->length())->toBe(38);
    });

    it('is countable, iterable and json-serialisable', function (): void {
        $collection = new PeriodCollection(period('2026-01-01', '2026-01-10'));

        expect($collection)->toHaveCount(1)
            ->and(iterator_to_array($collection))->toHaveCount(1)
            ->and(json_decode(json_encode($collection), true)[0]['precision'])->toBe('day');
    });

    it('is empty when it holds nothing', function (): void {
        expect((new PeriodCollection)->isEmpty())->toBeTrue()
            ->and((new PeriodCollection)->boundaries())->toBeNull()
            ->and((new PeriodCollection)->gaps())->toBeEmpty();
    });
});

describe('the visualizer', function (): void {
    it('draws each block on a shared timeline', function (): void {
        $chart = new Visualizer(20)->visualize([
            'sales'   => period('2026-01-01', '2026-01-10'),
            'support' => period('2026-01-05', '2026-01-15'),
        ]);

        expect($chart)->toContain('sales')
            ->and($chart)->toContain('support')
            ->and(substr_count($chart, "\n"))->toBe(1);

        // The later block starts further right, which is the only thing the
        // picture has to get right.
        [$first, $second] = explode("\n", $chart);
        expect(strpos($second, '['))->toBeGreaterThan(strpos($first, '['));
    });

    it('draws nothing for nothing', function (): void {
        expect((new Visualizer)->visualize([]))->toBe('');
    });
});
