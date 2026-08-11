<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\Core\Presentation;

use JsonSerializable;
use Simtabi\Laranail\Chrono\Core\Enums\ZoneField;
use Stringable;

/**
 * One zone, rendered for display.
 *
 * A real object rather than an array, so `->forObjects()` gives something with a shape an IDE and a
 * static analyser can both see. It still serialises to exactly the array the other output modes
 * produce, so nothing diverges between the object and JSON views.
 *
 * Only the fields that were asked for are populated; the rest are null. `toArray()` omits them
 * entirely rather than emitting a wall of nulls, which is the difference between a 2-field select
 * payload and a 18-field one.
 */
final readonly class PresentedZone implements JsonSerializable, Stringable
{
    /** @param array<string, scalar|null> $fields */
    public function __construct(
        public string $id,
        private array $fields,
    ) {}

    public function get(ZoneField $field): string|int|float|bool|null
    {
        return $this->fields[$field->value] ?? null;
    }

    public function has(ZoneField $field): bool
    {
        return array_key_exists($field->value, $this->fields);
    }

    public function label(): string
    {
        return (string) ($this->fields[ZoneField::Label->value] ?? $this->id);
    }

    /** @return array<string, scalar|null> */
    public function toArray(): array
    {
        return $this->fields;
    }

    /** @return array<string, scalar|null> */
    public function jsonSerialize(): array
    {
        return $this->fields;
    }

    public function __toString(): string
    {
        return $this->label();
    }

    /** @return array<string, scalar|null> */
    public function __debugInfo(): array
    {
        return $this->fields;
    }
}
