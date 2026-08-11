<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Config;

use Simtabi\Laranail\Chrono\Core\Enums\SelectShape;

/**
 * The default shape and placeholder for a timezone picker.
 *
 * Separate from {@see DisplayOptions} because a picker's shape is a layout decision while an offset
 * format is a rendering one, and applications change them for different reasons.
 */
final readonly class SelectOptions
{
    public function __construct(
        public SelectShape $shape = SelectShape::Grouped,
        public ?string $placeholder = null,
    ) {}

    /** @param array<string, mixed> $config */
    public static function fromArray(array $config): self
    {
        $shape = $config['shape'] ?? null;
        $placeholder = $config['placeholder'] ?? null;

        return new self(
            shape: $shape instanceof SelectShape
                ? $shape
                : SelectShape::tryFrom(is_string($shape) ? $shape : '') ?? SelectShape::Grouped,
            placeholder: is_string($placeholder) && $placeholder !== '' ? $placeholder : null,
        );
    }
}
