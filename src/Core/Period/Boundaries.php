<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Period;

use Simtabi\Laranail\Enumerator\Attributes\Label;
use Simtabi\Laranail\Enumerator\Concerns\HasEnumeratorBehavior;
use Simtabi\Laranail\Enumerator\Contracts\Enumerator;

/**
 * Which of a period's own endpoints belong to it.
 *
 * The question this answers is whether two periods that meet at a moment
 * overlap or merely touch. A booking that ends at 10:00 and one that starts at
 * 10:00 collide under `IncludeAll` and do not under `ExcludeEnd`, and neither
 * answer is universally right: a hotel night and a meeting room want different
 * ones.
 *
 * Bit masks again, so "is the start included" is a mask test rather than four
 * comparisons.
 */
enum Boundaries: int implements Enumerator
{
    use HasEnumeratorBehavior;

    #[Label('Include all')] case IncludeAll = 0;

    #[Label('Exclude start')] case ExcludeStart = 0b10;

    #[Label('Exclude end')] case ExcludeEnd = 0b01;

    #[Label('Exclude all')] case ExcludeAll = 0b11;

    public function startIncluded(): bool
    {
        return ! $this->startExcluded();
    }

    public function startExcluded(): bool
    {
        return ($this->value & self::ExcludeStart->value) === self::ExcludeStart->value;
    }

    public function endIncluded(): bool
    {
        return ! $this->endExcluded();
    }

    public function endExcluded(): bool
    {
        return ($this->value & self::ExcludeEnd->value) === self::ExcludeEnd->value;
    }
}
