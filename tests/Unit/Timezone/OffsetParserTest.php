<?php

declare(strict_types=1);

use Simtabi\Laranail\Chrono\Core\Exception\InvalidOffset;
use Simtabi\Laranail\Chrono\Core\Timezone\Support\OffsetParser;

it('parses every spelling an application might send', function (string $input, int $expected): void {
    expect(OffsetParser::tryParse($input))->toBe($expected);
})->with('offset spellings');

/**
 * `-0530` is genuinely ambiguous: it is both a valid integer and a compact offset. Signed forms are
 * tried first, so it reads as -05:30 rather than -530 seconds — which is what a naive
 * integer-first parser produces, and what this package did until a test caught it.
 */
it('reads a signed compact value as an offset, not as seconds', function (): void {
    expect(OffsetParser::tryParse('-0530'))->toBe(-19800)
        ->and(OffsetParser::tryParse('-19800'))->toBe(-19800);
});

it('refuses values that are not offsets', function (string $input): void {
    expect(OffsetParser::tryParse($input))->toBeNull();
})->with(['not a time', '+99:00', '3 o\'clock', '', '+03:99']);

it('throws when asked to parse strictly', function (): void {
    (void) OffsetParser::parse('nonsense');
})->throws(InvalidOffset::class);
