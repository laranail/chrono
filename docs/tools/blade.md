# The picker component

A timezone select that works before its JavaScript does — and becomes a searchable combobox once it
loads.

```blade
<x-chrono::timezone-select name="timezone" :selected="$user->timezone" />
```

## Progressive enhancement, not a replacement

The component renders a **real `<select>` with real `<optgroup>`s**. With scripting disabled, before
the script loads, or if it fails to load, the field still lists every zone, still submits, and is
still keyboard- and screen-reader-navigable — because it is a native control rather than a `div`
pretending to be one.

The script then upgrades it in place. The native select stays in the DOM and stays the thing that
submits; it is only hidden from sight and from the accessibility tree, since the combobox now speaks
for it.

Selecting an option assigns to the native select and dispatches `input` and `change`, so anything
already listening to the field — validation, Livewire, a framework binding — sees an ordinary event.

## Nothing inline

Options travel in a `<script type="application/json">` block and settings in `data-` attributes, so
a strict Content Security Policy needs no `unsafe-inline`. Nothing is passed in a script tag.

The JSON is hex-escaped, so a translated country name or a custom label template cannot close the
`<script>` element early no matter what it contains.

```html
<div data-chrono-select
     data-chrono-searchable="true"
     data-chrono-search-placeholder="Search timezones"
     data-chrono-empty="No timezone matches that search."
     dir="ltr">
    <select name="timezone" data-chrono-select-input> … </select>
    <script type="application/json" data-chrono-options>[ … ]</script>
</div>
```

## The script

```bash
php artisan vendor:publish --tag=laranail::chrono-assets
```

```html
<script src="{{ asset('vendor/laranail/chrono/chrono-select.js') }}" defer></script>
```

No dependencies, no build step, no framework — plain ES5-compatible JavaScript in one file. It binds
every `[data-chrono-select]` on load, and exposes `window.chronoSelect.enhance(scope)` so markup
added later, by a modal or a Livewire update, can be enhanced too.

Search matches on the pre-lowercased token the presenter emits — identifier, city, country,
abbreviation and offset — so no normalising happens per keystroke.

## Accessibility

A `combobox` input with an `aria-controls` `listbox`, `aria-expanded`, and `aria-activedescendant`
tracking the highlighted option. Arrow keys move, Enter selects, Escape closes. A `<label for>`
pointing at the original select is retargeted to the input so clicking it still focuses the field.

Options are chosen on `mousedown` rather than `click`, because blur would otherwise close the list
before a click ever landed.

## Props

Every appearance prop falls back to configuration rather than to a literal, so an application sets
its picker once in `laranail.chrono` and no template repeats it. Passing a prop overrides it for that
field only.

| Prop | Default | |
|---|---|---|
| `name` | `timezone` | |
| `selected` | — | Current value |
| `id` | derived from `name` | |
| `shape` | `select.shape` | `flat`, `grouped`, `formed`, `payload` — sets grouping and label together |
| `group` | from the shape | `continent`, `country`, `offset`, `none`. Overrides the shape's grouping |
| `preset` | `form` | Any `PresentationPreset`. Left to the shape when `shape="payload"` |
| `offset-format` | `display.offset_format` | Any `OffsetFormat` |
| `label-template` | from the shape | See [Presentation](presentation.md) |
| `locale` | app locale | Also sets `dir` |
| `searchable` | `true` | `false` leaves the plain select |
| `required` / `disabled` | `false` | |
| `placeholder` | `select.placeholder`, else translated | |
| `search-placeholder` / `empty-message` | translated | |

Anything else is merged onto the wrapper, so `class` and `x-data` pass through.

`shape` is the one to reach for first: it carries the grouping and the label template as a pair, so
`shape="formed"` gives `Africa/Nairobi (UTC +03:00)` under an `Africa` optgroup without naming either.
`group` and `label-template` are refinements applied after it, so they always win.

## Examples

```blade
{{-- Grouped by country, with flags and the local time in each label --}}
<x-chrono::timezone-select
    name="timezone"
    group="country"
    label-template="{flag} {city} — {time} ({gmt})" />

{{-- A plain select, no enhancement --}}
<x-chrono::timezone-select name="timezone" :searchable="false" />

{{-- Right-to-left --}}
<x-chrono::timezone-select name="timezone" locale="ar" />

{{-- One flat field in an application whose default shape is grouped --}}
<x-chrono::timezone-select name="timezone" shape="flat" />
```

## Styling

Unstyled by design — it emits class names and no CSS, so it inherits whatever your form controls
already look like. The hooks are `.chrono-select`, `__input`, `__list`, `__group`, `__option`
(`.is-active` when highlighted), `__flag`, `__label` and `__empty`.

Publish the view to change the markup:

```bash
php artisan vendor:publish --tag=laranail::chrono-views
```

---

[← Docs index](../../README.md#documentation)