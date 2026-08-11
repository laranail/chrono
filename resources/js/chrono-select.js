/*!
 * chrono-select — progressive enhancement for laranail/chrono's timezone picker.
 *
 * No dependencies, no build step, no framework. Drop it on the page and every
 * `[data-chrono-select]` becomes a searchable combobox; leave it off and the native <select>
 * underneath keeps working.
 *
 * It reads its options from a `<script type="application/json">` block and its settings from
 * `data-` attributes, so nothing is passed in inline script and a strict Content Security Policy
 * needs no 'unsafe-inline'.
 */
(function () {
    'use strict';

    var ATTR = 'data-chrono-select';
    var ENHANCED = 'data-chrono-enhanced';

    /** Match on the pre-lowercased search token the presenter emits, so no normalising per keystroke. */
    function matches(option, needle) {
        if (!needle) return true;
        return (option.search || (option.id + ' ' + option.label).toLowerCase()).indexOf(needle) !== -1;
    }

    function el(tag, attrs, text) {
        var node = document.createElement(tag);
        for (var key in attrs) {
            if (Object.prototype.hasOwnProperty.call(attrs, key)) node.setAttribute(key, attrs[key]);
        }
        if (text != null) node.textContent = text;
        return node;
    }

    function ChronoSelect(root) {
        this.root = root;
        this.select = root.querySelector('[data-chrono-select-input]');
        if (!this.select) return;

        var payload = root.querySelector('[data-chrono-options]');
        try {
            this.options = payload ? JSON.parse(payload.textContent) : [];
        } catch (e) {
            // A malformed payload must not take the field with it: leave the native select alone.
            return;
        }

        this.emptyText = root.getAttribute('data-chrono-empty') || 'No match';
        this.activeIndex = -1;
        this.filtered = this.options.slice();

        this.build();
        this.bind();
    }

    ChronoSelect.prototype.build = function () {
        var id = this.select.id + '-combobox';

        this.input = el('input', {
            type: 'text',
            role: 'combobox',
            id: id,
            class: 'chrono-select__input',
            autocomplete: 'off',
            'aria-expanded': 'false',
            'aria-autocomplete': 'list',
            'aria-controls': id + '-list',
            placeholder: this.root.getAttribute('data-chrono-search-placeholder') || 'Search timezones',
        });

        this.list = el('ul', {
            role: 'listbox',
            id: id + '-list',
            class: 'chrono-select__list',
            hidden: 'hidden',
        });

        if (this.select.disabled) this.input.setAttribute('disabled', 'disabled');

        // The native select stays in the DOM and stays the thing that submits; it is only hidden
        // from sight and from the accessibility tree, since the combobox now speaks for it.
        this.select.setAttribute('tabindex', '-1');
        this.select.setAttribute('aria-hidden', 'true');
        this.select.style.position = 'absolute';
        this.select.style.opacity = '0';
        this.select.style.pointerEvents = 'none';
        this.select.style.width = '1px';
        this.select.style.height = '1px';

        var label = document.querySelector('label[for="' + this.select.id + '"]');
        if (label) label.setAttribute('for', id);

        this.root.insertBefore(this.input, this.select);
        this.root.appendChild(this.list);

        this.syncFromSelect();
    };

    ChronoSelect.prototype.syncFromSelect = function () {
        var current = this.select.value;
        for (var i = 0; i < this.options.length; i++) {
            if (this.options[i].id === current) {
                this.input.value = this.options[i].label;
                return;
            }
        }
        this.input.value = '';
    };

    ChronoSelect.prototype.render = function () {
        var self = this;
        this.list.textContent = '';

        if (this.filtered.length === 0) {
            this.list.appendChild(el('li', { class: 'chrono-select__empty', role: 'presentation' }, this.emptyText));
            return;
        }

        var lastGroup = null;

        this.filtered.forEach(function (option, index) {
            var group = option.continent || option.group;

            if (group && group !== lastGroup) {
                self.list.appendChild(el('li', { class: 'chrono-select__group', role: 'presentation' }, group));
                lastGroup = group;
            }

            var item = el('li', {
                role: 'option',
                class: 'chrono-select__option',
                id: self.input.id + '-opt-' + index,
                'data-value': option.id,
                'aria-selected': option.id === self.select.value ? 'true' : 'false',
            });

            if (option.flag) item.appendChild(el('span', { class: 'chrono-select__flag' }, option.flag));
            item.appendChild(el('span', { class: 'chrono-select__label' }, option.label));

            if (index === self.activeIndex) item.classList.add('is-active');

            self.list.appendChild(item);
        });
    };

    ChronoSelect.prototype.open = function () {
        this.list.hidden = false;
        this.input.setAttribute('aria-expanded', 'true');
        this.render();
    };

    ChronoSelect.prototype.close = function () {
        this.list.hidden = true;
        this.input.setAttribute('aria-expanded', 'false');
        this.input.removeAttribute('aria-activedescendant');
        this.activeIndex = -1;
        this.syncFromSelect();
    };

    ChronoSelect.prototype.filter = function (term) {
        var needle = term.trim().toLowerCase();
        this.filtered = this.options.filter(function (option) {
            return matches(option, needle);
        });
        this.activeIndex = this.filtered.length > 0 ? 0 : -1;
        this.render();
        this.reflectActive();
    };

    ChronoSelect.prototype.reflectActive = function () {
        var items = this.list.querySelectorAll('[role="option"]');
        for (var i = 0; i < items.length; i++) items[i].classList.remove('is-active');

        if (this.activeIndex < 0 || !items[this.activeIndex]) {
            this.input.removeAttribute('aria-activedescendant');
            return;
        }

        var active = items[this.activeIndex];
        active.classList.add('is-active');
        this.input.setAttribute('aria-activedescendant', active.id);

        if (active.scrollIntoView) active.scrollIntoView({ block: 'nearest' });
    };

    ChronoSelect.prototype.choose = function (identifier) {
        // Assigning to the native select and dispatching `change` means anything already listening
        // to the field — validation, Livewire, a framework binding — sees a normal event.
        this.select.value = identifier;
        this.select.dispatchEvent(new Event('input', { bubbles: true }));
        this.select.dispatchEvent(new Event('change', { bubbles: true }));
        this.close();
    };

    ChronoSelect.prototype.bind = function () {
        var self = this;

        this.input.addEventListener('focus', function () {
            self.filter('');
            self.input.select();
            self.open();
        });

        this.input.addEventListener('input', function () {
            if (self.list.hidden) self.open();
            self.filter(self.input.value);
        });

        this.input.addEventListener('keydown', function (event) {
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                if (self.list.hidden) self.open();
                var step = event.key === 'ArrowDown' ? 1 : -1;
                var count = self.filtered.length;
                if (count === 0) return;
                self.activeIndex = (self.activeIndex + step + count) % count;
                self.reflectActive();
                return;
            }

            if (event.key === 'Enter' && !self.list.hidden && self.activeIndex >= 0) {
                event.preventDefault();
                self.choose(self.filtered[self.activeIndex].id);
                return;
            }

            if (event.key === 'Escape') self.close();
        });

        this.list.addEventListener('mousedown', function (event) {
            // mousedown, not click: blur would close the list before a click ever landed.
            var option = event.target.closest ? event.target.closest('[data-value]') : null;
            if (!option) return;
            event.preventDefault();
            self.choose(option.getAttribute('data-value'));
        });

        document.addEventListener('click', function (event) {
            if (!self.root.contains(event.target)) self.close();
        });

        this.select.addEventListener('change', function () {
            if (self.list.hidden) self.syncFromSelect();
        });
    };

    function enhance(scope) {
        var roots = (scope || document).querySelectorAll('[' + ATTR + ']:not([' + ENHANCED + '])');

        for (var i = 0; i < roots.length; i++) {
            var root = roots[i];
            if (root.getAttribute('data-chrono-searchable') === 'false') continue;
            root.setAttribute(ENHANCED, 'true');
            new ChronoSelect(root);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            enhance();
        });
    } else {
        enhance();
    }

    // Exposed so markup added later — a modal, a Livewire update — can be enhanced too.
    window.chronoSelect = { enhance: enhance };
})();
