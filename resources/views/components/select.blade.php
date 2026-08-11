{{--
    A native <select> first. The enhancement script upgrades it in place; with scripting off it
    remains a complete, submittable, accessible control rather than a broken one.

    The option data travels in a JSON script block rather than an inline assignment, so a strict
    Content Security Policy needs no 'unsafe-inline'.
--}}
<div
    data-chrono-select
    data-chrono-searchable="{{ $searchable ? 'true' : 'false' }}"
    data-chrono-search-placeholder="{{ $searchPlaceholderText }}"
    data-chrono-empty="{{ $emptyText }}"
    dir="{{ $direction }}"
    {{ $attributes->merge(['class' => 'chrono-select']) }}
>
    <select
        id="{{ $fieldId }}"
        name="{{ $name }}"
        data-chrono-select-input
        @if ($required) required @endif
        @if ($disabled) disabled @endif
    >
        @if ($placeholderText !== '')
            <option value="">{{ $placeholderText }}</option>
        @endif

        @foreach ($grouped as $groupLabel => $zones)
            @if ($groupLabel === '')
                @foreach ($zones as $identifier => $label)
                    <option value="{{ $identifier }}" @selected($identifier === $selected)>{{ $label }}</option>
                @endforeach
            @else
                <optgroup label="{{ $groupLabel }}">
                    @foreach ($zones as $identifier => $label)
                        <option value="{{ $identifier }}" @selected($identifier === $selected)>{{ $label }}</option>
                    @endforeach
                </optgroup>
            @endif
        @endforeach
    </select>

    {{-- Hex-escaped: a label or a translated country name is application data, and `</script>`
         inside a JSON block ends the element early wherever the JSON encoder is unaware of HTML. --}}
    <script type="application/json" data-chrono-options>@json($options, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE)</script>
</div>
