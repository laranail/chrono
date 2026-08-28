<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Timezone\Value;

use NoDiscard;
use Stringable;
use DateTimeZone;
use JsonSerializable;
use Simtabi\Laranail\Chrono\Core\Enums\OffsetFormat;
use Simtabi\Laranail\Chrono\Core\Exception\InvalidOffset;

/**
 * A UTC offset in seconds.
 *
 * The valid range is ±18 hours, not the ±14 hours you would guess from the modern extremes
 * (`Pacific/Kiritimati` at +14:00 and `Etc/GMT+12`). Historical LMT offsets in the IANA database
 * reach **−15:56:00** (`Asia/Manila`) and **+15:13:00** (`America/Metlakatla`), so a ±14 hour guard
 * rejects real data. Sub-minute offsets are likewise real and are preserved rather than rounded.
 */
final readonly class Offset implements JsonSerializable, Stringable
{
    /** ±18 hours. Wide enough for every LMT offset in the database, narrow enough to catch a bug. */
    public const int MAX_SECONDS = 64800;

    public function __construct(public int $seconds)
    {
        if ($seconds < -self::MAX_SECONDS || $seconds > self::MAX_SECONDS) {
            throw InvalidOffset::outOfRange($seconds, self::MAX_SECONDS);
        }
    }

    public function __toString(): string
    {
        return $this->format();
    }

    /** @return array<string, string> */
    public function __debugInfo(): array
    {
        return [
            'offset'  => $this->format(),
            'seconds' => (string) $this->seconds,
        ];
    }

    public static function fromSeconds(int $seconds): self
    {
        return new self($seconds);
    }

    public static function fromMinutes(int $minutes): self
    {
        return new self($minutes * 60);
    }

    public static function fromHours(float $hours): self
    {
        return new self((int) round($hours * 3600));
    }

    public static function utc(): self
    {
        return new self(0);
    }

    #[NoDiscard]
    public function withSeconds(int $seconds): self
    {
        return clone ($this, ['seconds' => $seconds]);
    }

    public function minutes(): int
    {
        return intdiv($this->seconds, 60);
    }

    public function hours(): float
    {
        return $this->seconds / 3600;
    }

    public function hoursPart(): int
    {
        return intdiv(abs($this->seconds), 3600);
    }

    public function minutesPart(): int
    {
        return intdiv(abs($this->seconds) % 3600, 60);
    }

    public function secondsPart(): int
    {
        return abs($this->seconds) % 60;
    }

    /** -1 behind UTC, 0 at UTC, 1 ahead. */
    public function sign(): int
    {
        return $this->seconds <=> 0;
    }

    public function isUtc(): bool
    {
        return $this->seconds === 0;
    }

    public function isAheadOfUtc(): bool
    {
        return $this->seconds > 0;
    }

    public function isWholeHours(): bool
    {
        return $this->seconds % 3600 === 0;
    }

    /** False for the historical LMT offsets that carry seconds, e.g. `Asia/Manila` at −15:56:00. */
    public function isWholeMinutes(): bool
    {
        return $this->seconds % 60 === 0;
    }

    public function format(OffsetFormat $format = OffsetFormat::Colon): string
    {
        return $format->format($this->seconds);
    }

    /**
     * A `DateTimeZone` of type 1 (offset). Note this zone has no rules and no location: calling
     * `getTransitions()` on it returns `false`, not an empty array.
     */
    public function toDateTimeZone(): DateTimeZone
    {
        return new DateTimeZone($this->format(OffsetFormat::Colon));
    }

    #[NoDiscard]
    public function plus(self $other): self
    {
        return new self($this->seconds + $other->seconds);
    }

    #[NoDiscard]
    public function minus(self $other): self
    {
        return new self($this->seconds - $other->seconds);
    }

    #[NoDiscard]
    public function negated(): self
    {
        return new self(-$this->seconds);
    }

    public function compareTo(self $other): int
    {
        return $this->seconds <=> $other->seconds;
    }

    public function equals(self $other): bool
    {
        return $this->seconds === $other->seconds;
    }

    /** @return array{seconds: int, label: string, hours: float} */
    public function toArray(): array
    {
        return [
            'seconds' => $this->seconds,
            'label'   => $this->format(),
            'hours'   => $this->hours(),
        ];
    }

    public function jsonSerialize(): string
    {
        return $this->format();
    }
}
