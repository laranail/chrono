<?php

declare(strict_types=1);

use Simtabi\Laranail\Chrono\Core\Conversion\ConvertedTime;
use Simtabi\Laranail\Chrono\Core\Conversion\TimeConverter;
use Simtabi\Laranail\Chrono\Core\Enums\GapPolicy;
use Simtabi\Laranail\Chrono\Core\Exception\SkippedLocalTime;
use Simtabi\Laranail\Chrono\Core\Timezone\Timezones;

beforeEach(function (): void {
    $this->converter = new TimeConverter(new Timezones);
});

it('answers the basic question', function (): void {
    $result = $this->converter
        ->of('2026-06-15 09:00')
        ->from('Africa/Nairobi')
        ->to('Europe/London')
        ->format('Y-m-d H:i')
        ->first();

    expect($result)->toBeInstanceOf(ConvertedTime::class)
        ->and($result->formatted())->toBe('2026-06-15 07:00')
        ->and($result->abbreviation())->toBe('BST');
});

/** A single value or a list, on both sides, so a caller never has to pick a form. */
it('accepts one or many, either way round', function (): void {
    $single = $this->converter->of('2026-06-15 09:00')->from('UTC')->to('Asia/Tokyo');
    $manyZones = $this->converter->of('2026-06-15 09:00')->from('UTC')->to(['Asia/Tokyo', 'Europe/London']);
    $manyTimes = $this->converter->of(['2026-06-15 09:00', '2026-06-15 17:00'])->from('UTC')->to('Asia/Tokyo');
    $both = $this->converter->of(['2026-06-15 09:00', '2026-06-15 17:00'])->from('UTC')->to(['Asia/Tokyo', 'Europe/London']);

    expect($single->get())->toHaveCount(1)
        ->and($manyZones->get())->toHaveCount(2)
        ->and($manyTimes->get())->toHaveCount(2)
        ->and($both->get())->toHaveCount(4);
});

it('keeps every conversion on the same instant', function (): void {
    $results = $this->converter->of('2026-06-15 09:00')->from('Africa/Nairobi')
        ->to(['Europe/London', 'Asia/Tokyo', 'America/New_York'])->get();

    $instants = array_unique(array_map(static fn (ConvertedTime $c): int => $c->instant->getTimestamp(), $results));

    expect($instants)->toHaveCount(1);
});

it('builds a grid for a meeting across offices', function (): void {
    $table = $this->converter
        ->of(['2026-06-15 09:00', '2026-06-15 14:00'])
        ->from('Africa/Nairobi')
        ->to(['Europe/London', 'Asia/Tokyo'])
        ->format('H:i')
        ->table();

    expect($table)->toHaveCount(2)
        ->and($table[0])->toBe(['Europe/London' => '07:00', 'Asia/Tokyo' => '15:00'])
        ->and($table[1])->toBe(['Europe/London' => '12:00', 'Asia/Tokyo' => '20:00']);
});

/** A wall-clock reading is not an instant; it goes through the source zone's DST policies. */
it('routes a bare reading through the daylight-saving policies', function (): void {
    $newYork = $this->converter->of('2026-03-08 02:30')->from('America/New_York')->to('UTC');

    expect($newYork->format('H:i')->first()->formatted())->toBe('07:30');

    expect(fn () => $newYork->onGap(GapPolicy::Throw)->get())
        ->toThrow(SkippedLocalTime::class);
});

it('honours an instant that already carries its own zone', function (): void {
    $instant = new DateTimeImmutable('2026-06-15 09:00', new DateTimeZone('Africa/Nairobi'));

    expect($this->converter->of($instant)->to('Europe/London')->format('H:i')->first()->formatted())
        ->toBe('07:00');
});

it('answers for a whole country', function (): void {
    $results = $this->converter->of('2026-06-15 12:00')->from('UTC')
        ->toCountry('KE')->format('Y-m-d H:i')->keyed();

    expect($results)->toHaveKey('Africa/Nairobi')
        ->and($results['Africa/Nairobi']->formatted())->toBe('2026-06-15 15:00');
});

it('reports how far apart two zones are', function (): void {
    $keyed = $this->converter->of('2026-06-15 12:00')->from('UTC')
        ->to(['Africa/Nairobi', 'America/New_York'])->keyed();

    expect($keyed['Africa/Nairobi']->offsetFrom($keyed['America/New_York']))->toBe(7 * 3600);
});

it('renders for an API and as JSON', function (): void {
    $converter = $this->converter->of('2026-06-15 09:00')->from('UTC')->to('Asia/Tokyo');

    expect($converter->forApi()[0])->toHaveKeys(['zone', 'instant', 'local', 'offset_label', 'abbreviation', 'dst'])
        ->and(json_decode($converter->forJson(), true))->toBeArray();
});

it('defaults to UTC when no target is named', function (): void {
    expect($this->converter->of('2026-06-15 09:00')->from('Africa/Nairobi')->first()->zone->identifier)
        ->toBe('UTC');
});

it('never mutates the converter it was called on', function (): void {
    $base = $this->converter->of('2026-06-15 09:00')->from('UTC')->to('Asia/Tokyo');
    $extended = $base->to('Europe/London');

    expect($base->get())->toHaveCount(1)
        ->and($extended->get())->toHaveCount(2);
});
