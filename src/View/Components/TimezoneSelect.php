<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\View\Components;

use Illuminate\View\Component;
use Illuminate\Contracts\View\View;
use Simtabi\Laranail\Chrono\Chrono;
use Simtabi\Laranail\Chrono\Core\Enums\GroupBy;
use Simtabi\Laranail\Chrono\Core\Enums\SelectShape;
use Simtabi\Laranail\Chrono\Core\Enums\OffsetFormat;
use Simtabi\Laranail\Chrono\Core\Config\SelectOptions;
use Simtabi\Laranail\Chrono\Core\Enums\PresentationPreset;
use Simtabi\Laranail\Chrono\Core\Presentation\TimezonePresenter;

/**
 * A timezone picker that works before its JavaScript does.
 *
 * The component renders a real `<select>` with real `<optgroup>`s. With scripting disabled, or
 * before the script loads, or if it fails to load, the field still shows every zone, still submits,
 * and is still keyboard- and screen-reader-navigable — because it is a native control, not a
 * div pretending to be one.
 *
 * The enhancement layer then upgrades it in place into a searchable combobox. It reads its data
 * from a `<script type="application/json">` block rather than an inline assignment, so no
 * `unsafe-inline` is needed in a Content Security Policy, and it takes its configuration from
 * `data-` attributes so nothing has to be passed in JavaScript.
 *
 * Every appearance attribute defaults to null and falls back to configuration, so an application
 * sets its picker's shape once in `laranail.chrono.select` and no template repeats it. Passing an
 * attribute overrides it for that field only.
 */
final class TimezoneSelect extends Component
{
    public function __construct(
        public string $name = 'timezone',
        public ?string $selected = null,
        public ?string $id = null,
        public ?string $shape = null,
        public ?string $group = null,
        public ?string $preset = null,
        public ?string $offsetFormat = null,
        public ?string $labelTemplate = null,
        public ?string $locale = null,
        public bool $searchable = true,
        public bool $required = false,
        public bool $disabled = false,
        public ?string $placeholder = null,
        public ?string $searchPlaceholder = null,
        public ?string $emptyMessage = null,
    ) {}

    public function render(): View
    {
        $options = $this->options();
        $shape = $this->shapeFor($options);

        $presenter = $this->presenter($shape);
        $grouped = $presenter->forSelect();
        $groupBy = $this->groupFor($shape);

        // larastan cannot resolve a package-namespaced view during analysis; the view exists and
        // is asserted by BladeComponentTest.
        /** @var view-string $view */
        $view = 'laranail-chrono::components.select';

        return view($view, [
            'fieldId'         => $this->id ?? 'chrono-tz-' . substr(md5($this->name), 0, 8),
            'grouped'         => $groupBy === GroupBy::None ? ['' => $grouped] : $grouped,
            'options'         => $presenter->flat()->forApi(),
            'direction'       => $this->isRightToLeft($this->localeFor()) ? 'rtl' : 'ltr',
            'placeholderText' => $this->placeholder
                ?? $options->placeholder
                ?? __('laranail-chrono::messages.select.placeholder'),
            'searchPlaceholderText' => $this->searchPlaceholder ?? __('laranail-chrono::messages.select.search'),
            'emptyText'             => $this->emptyMessage ?? __('laranail-chrono::messages.select.empty'),
        ]);
    }

    private function presenter(SelectShape $shape): TimezonePresenter
    {
        $presenter = app(Chrono::class)
            ->present()
            ->shape($shape)
            ->locale($this->localeFor());

        // Only when asked. `shape()` already chose a field set — `payload` wants the API one — and
        // unconditionally overwriting it here would make `shape="payload"` mean nothing.
        if ($this->preset !== null) {
            $presenter = $presenter->preset(PresentationPreset::tryFrom($this->preset) ?? PresentationPreset::Form);
        } elseif ($shape !== SelectShape::Payload) {
            $presenter = $presenter->preset(PresentationPreset::Form);
        }

        // `group` and `label` are refinements of the shape, so they are applied after it rather
        // than before — otherwise the shape would silently overwrite whatever the template asked for.
        if ($this->group !== null) {
            $presenter = $presenter->groupBy(GroupBy::tryFrom($this->group) ?? GroupBy::Continent);
        }

        if ($this->labelTemplate !== null) {
            $presenter = $presenter->label($this->labelTemplate);
        }

        if ($this->offsetFormat !== null) {
            return $presenter->offsetFormat(OffsetFormat::tryFrom($this->offsetFormat) ?? OffsetFormat::Utc);
        }

        return $presenter;
    }

    private function options(): SelectOptions
    {
        return SelectOptions::fromArray((array) config('laranail.chrono.select', []));
    }

    private function shapeFor(SelectOptions $options): SelectShape
    {
        return SelectShape::tryFrom((string) $this->shape) ?? $options->shape;
    }

    /** The grouping actually in force, once an explicit `group` attribute has had its say. */
    private function groupFor(SelectShape $shape): GroupBy
    {
        if ($this->group !== null) {
            return GroupBy::tryFrom($this->group) ?? GroupBy::Continent;
        }

        return match ($shape) {
            SelectShape::Grouped, SelectShape::Formed => GroupBy::Continent,
            SelectShape::Flat, SelectShape::Payload   => GroupBy::None,
        };
    }

    private function localeFor(): string
    {
        return $this->locale ?? app()->getLocale();
    }

    private function isRightToLeft(string $locale): bool
    {
        return function_exists('locale_is_right_to_left') && locale_is_right_to_left($locale);
    }
}
