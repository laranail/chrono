<?php

declare(strict_types=1);

use Simtabi\Laranail\Chrono\Core\Enums\OffsetFormat;
use Simtabi\Laranail\Chrono\Core\Exception\InvalidOffset;
use Simtabi\Laranail\Chrono\Core\Timezone\Value\Offset;

it('renders every format', function (OffsetFormat $format, string $expected): void {
    expect(new Offset(10800)->format($format))->toBe($expected);
})->with([
    [OffsetFormat::Colon, '+03:00'],
    [OffsetFormat::Compact, '+0300'],
    [OffsetFormat::Short, '+3'],
    [OffsetFormat::Gmt, 'GMT+03:00'],
    [OffsetFormat::Utc, 'UTC +03:00'],
    [OffsetFormat::Iso8601, '+03:00'],
    [OffsetFormat::Seconds, '10800'],
]);

it('renders zero as Z in ISO 8601 and bare elsewhere', function (): void {
    expect(new Offset(0)->format(OffsetFormat::Iso8601))->toBe('Z')
        ->and(new Offset(0)->format(OffsetFormat::Gmt))->toBe('GMT')
        ->and(new Offset(0)->format(OffsetFormat::Utc))->toBe('UTC');
});

it('renders sub-hour offsets without truncating', function (string $identifier, int $seconds, string $expected): void {
    expect(new Offset($seconds)->format())->toBe($expected);
})->with('sub-hour offsets');

/**
 * The range is +/-18 hours rather than the +/-14 you would infer from Kiritimati and Etc/GMT+12.
 * Historical local mean times in the database go well past that, and rejecting them would mean
 * rejecting real data.
 */
it('accepts the historical extremes of the database', function (): void {
    expect(new Offset(-57368)->format())->toBe('-15:56:08')   // Asia/Manila LMT
        ->and(new Offset(54822)->format())->toBe('+15:13:42') // America/Metlakatla LMT
        ->and(new Offset(-57368)->isWholeMinutes())->toBeFalse();
});

it('rejects an offset beyond eighteen hours', function (): void {
    (void) new Offset(70000);
})->throws(InvalidOffset::class);

it('inverts the sign for Etc zones as the POSIX convention requires', function (): void {
    // Etc/GMT+5 is UTC-05:00. The name reads backwards and always has.
    expect(new Offset(-18000)->format())->toBe('-05:00');
});

it('stays immutable under clone-with', function (): void {
    $original = new Offset(10800);
    $changed = $original->withSeconds(3600);

    expect($original->seconds)->toBe(10800)
        ->and($changed->seconds)->toBe(3600)
        ->and($changed)->not->toBe($original);
});

it('does arithmetic and comparison', function (): void {
    $three = new Offset(10800);
    $one = new Offset(3600);

    expect($three->minus($one)->seconds)->toBe(7200)
        ->and($three->plus($one)->seconds)->toBe(14400)
        ->and($three->negated()->seconds)->toBe(-10800)
        ->and($three->compareTo($one))->toBe(1)
        ->and($three->equals(new Offset(10800)))->toBeTrue()
        ->and($three->sign())->toBe(1)
        ->and(new Offset(0)->isUtc())->toBeTrue();
});
