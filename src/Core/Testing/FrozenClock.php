<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Testing;

use NoDiscard;
use DateTimeZone;
use DateTimeImmutable;
use DateTimeInterface;
use Simtabi\Laranail\Chrono\Core\Contracts\Clock;

/**
 * A clock that does not move, so a test asserting daylight-saving behaviour means the same thing
 * in five years as it does the week it was written.
 */
final readonly class FrozenClock implements Clock
{
    public DateTimeImmutable $instant;

    public function __construct(DateTimeInterface|string $instant = '2026-06-15T12:00:00Z')
    {
        $this->instant = $instant instanceof DateTimeInterface
            ? DateTimeImmutable::createFromInterface($instant)->setTimezone(new DateTimeZone('UTC'))
            : new DateTimeImmutable($instant, new DateTimeZone('UTC'));
    }

    public function now(): DateTimeImmutable
    {
        return $this->instant;
    }

    #[NoDiscard]
    public function movedTo(DateTimeInterface|string $instant): self
    {
        return new self($instant);
    }
}
