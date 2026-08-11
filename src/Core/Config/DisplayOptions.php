<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Config;

use Simtabi\Laranail\Chrono\Core\Enums\OffsetFormat;

/**
 * How this application renders a zone and an instant, by default.
 *
 * The presenter and the converter both need an offset shape and a date format, and if they take
 * their own defaults the same zone renders as `UTC +03:00` in a picker and `+03:00` in an API
 * response of the same application. One object, read once at boot, is what keeps them agreeing.
 */
final readonly class DisplayOptions
{
    public function __construct(
        public OffsetFormat $offsetFormat = OffsetFormat::Utc,
        public string $dateTimeFormat = 'M j, Y H:i',
        public string $timeFormat = 'H:i',
        public ?string $locale = null,
    ) {}

    /** @param array<string, mixed> $config */
    public static function fromArray(array $config): self
    {
        $offset = $config['offset_format'] ?? null;
        $dateTime = $config['datetime_format'] ?? null;
        $time = $config['time_format'] ?? null;
        $locale = $config['locale'] ?? null;

        return new self(
            offsetFormat: $offset instanceof OffsetFormat
                ? $offset
                : OffsetFormat::tryFrom(is_string($offset) ? $offset : '') ?? OffsetFormat::Utc,
            dateTimeFormat: is_string($dateTime) && $dateTime !== '' ? $dateTime : 'M j, Y H:i',
            timeFormat: is_string($time) && $time !== '' ? $time : 'H:i',
            locale: is_string($locale) && $locale !== '' ? $locale : null,
        );
    }
}
