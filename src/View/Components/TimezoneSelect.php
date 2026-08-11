<?php

declare(strict_types=1);

namespace Simtabi\Laranail\Chrono\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;
use Simtabi\Laranail\Chrono\Chrono;
use Simtabi\Laranail\Chrono\Core\Enums\GroupBy;
use Simtabi\Laranail\Chrono\Core\Enums\OffsetFormat;
use Simtabi\Laranail\Chrono\Core\Enums\PresentationPreset;

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
 */
final class TimezoneSelect extends Component
{
    public function __construct(
        public string $name = 'timezone',
        public ?string $selected = null,
        public ?string $id = null,
        public string $group = 'continent',
        public string $preset = 'form',
        public string $offsetFormat = 'utc',
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
        $presenter = app(Chrono::class)
            ->present()
            ->preset(PresentationPreset::tryFrom($this->preset) ?? PresentationPreset::Form)
            ->groupBy(GroupBy::tryFrom($this->group) ?? GroupBy::Continent)
            ->offsetFormat(OffsetFormat::tryFrom($this->offsetFormat) ?? OffsetFormat::Utc);

        if ($this->labelTemplate !== null) {
            $presenter = $presenter->label($this->labelTemplate);
        }

        $locale = $this->locale ?? app()->getLocale();
        $presenter = $presenter->locale($locale);

        /** @var array<string, array<string, string>>|array<string, string> $grouped */
        $grouped = $presenter->forSelect();

        // larastan cannot resolve a package-namespaced view during analysis; the view exists and
        // is asserted by BladeComponentTest.
        /** @var view-string $view */
        $view = 'chrono::components.select';

        return view($view, [
            'fieldId' => $this->id ?? 'chrono-tz-' . substr(md5($this->name), 0, 8),
            'grouped' => $this->group === GroupBy::None->value ? ['' => $grouped] : $grouped,
            'options' => $presenter->flat()->forApi(),
            'direction' => $this->isRightToLeft($locale) ? 'rtl' : 'ltr',
            'placeholderText' => $this->placeholder ?? __('chrono::messages.select.placeholder'),
            'searchPlaceholderText' => $this->searchPlaceholder ?? __('chrono::messages.select.search'),
            'emptyText' => $this->emptyMessage ?? __('chrono::messages.select.empty'),
        ]);
    }

    private function isRightToLeft(string $locale): bool
    {
        return function_exists('locale_is_right_to_left') && locale_is_right_to_left($locale);
    }
}
