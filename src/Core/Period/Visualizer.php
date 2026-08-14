<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Period;

use DateTimeImmutable;

/**
 * Draws periods on a shared timeline, as text.
 *
 * Overlap bugs are hard to read as dates and obvious as bars. This exists for
 * test failures and `dd()`, where seeing that two spans touch rather than
 * overlap takes a second instead of a minute.
 *
 *     $visualizer->visualize(['sales' => $a, 'support' => $b]);
 *
 *     sales     [====================]
 *     support             [====================]
 */
final readonly class Visualizer
{
    public function __construct(private int $width = 27) {}

    public function withWidth(int $width): self
    {
        return new self($width);
    }

    /**
     * @param array<string, Period|PeriodCollection> $blocks label => what to draw
     */
    public function visualize(array $blocks): string
    {
        $bounds = $this->bounds($blocks);

        if (! $bounds instanceof Period) {
            return '';
        }

        // Keys arrive as int|string, because PHP normalises a numeric string key
        // to an integer however the caller wrote it.
        $widths = array_map(strlen(...), array_map(strval(...), array_keys($blocks)));
        $labelWidth = $widths === [] ? 0 : max($widths);
        $lines = [];

        foreach ($blocks as $label => $block) {
            $periods = $block instanceof PeriodCollection ? $block->all() : [$block];

            $lines[] = sprintf(
                '%s %s',
                str_pad($label, $labelWidth),
                $this->row($periods, $bounds),
            );
        }

        return implode("\n", $lines);
    }

    /**
     * One row: a blank line with a bar drawn wherever a period covers it.
     *
     * @param list<Period> $periods
     */
    private function row(array $periods, Period $bounds): string
    {
        $row = str_repeat(' ', $this->width);
        $total = max(1, $bounds->length() - 1);

        foreach ($periods as $period) {
            $start = $this->offset($period->includedStart(), $bounds, $total);
            $end = $this->offset($period->includedEnd(), $bounds, $total);

            // A period shorter than one cell still has to be visible, or a
            // single-day booking silently disappears from the picture.
            $row = substr_replace($row, '[', $start, 1);
            $row = substr_replace($row, ']', $end, 1);

            for ($cell = $start + 1; $cell < $end; $cell++) {
                $row = substr_replace($row, '=', $cell, 1);
            }
        }

        return $row;
    }

    private function offset(DateTimeImmutable $moment, Period $bounds, int $total): int
    {
        $elapsed = 0;
        $cursor = $bounds->includedStart();

        while ($cursor < $moment) {
            $elapsed++;
            $cursor = $cursor->modify($bounds->precision->interval());
        }

        return (int) round(($elapsed / $total) * ($this->width - 1));
    }

    /** @param array<string, Period|PeriodCollection> $blocks */
    private function bounds(array $blocks): ?Period
    {
        $all = new PeriodCollection;

        foreach ($blocks as $block) {
            $all = $block instanceof PeriodCollection
                ? $all->add(...$block->all())
                : $all->add($block);
        }

        return $all->boundaries();
    }
}
