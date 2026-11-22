'use strict';

(() => {
    const editors = document.querySelectorAll('[data-public-nav-editor]');

    for (const editor of editors) {
        const form = editor.querySelector('.admin-public-nav__form');
        const search = editor.querySelector('[data-public-nav-search]');
        const filter = editor.querySelector('[data-public-nav-filter]');
        let rows = Array.from(editor.querySelectorAll('[data-public-nav-row]'));
        const counter = editor.querySelector('[data-public-nav-count]');
        const empty = editor.querySelector('[data-public-nav-empty]');
        const status = editor.querySelector('[data-public-nav-status]');
        const summary = editor.querySelector('.admin-public-nav__summary small');
        if (!form || !search || !filter || !counter || !empty || !status) continue;

        // Each request updates exactly one field; serialize requests even during rapid edits.
        const saved = new Map();
        const pending = new Map();
        const timers = new Map();
        const fields = ['label', 'main', 'footer', 'order'];
        let saving = false;
        let reordering = false;

        const controlFor = (row, field) => row.querySelector('[data-public-nav-field="' + field + '"]');
        const keyFor = (row, field) => row.dataset.publicNavId + ':' + field;
        const read = (row, field) => {
            const control = controlFor(row, field);
            return field === 'main' || field === 'footer'
                ? control.getAttribute('aria-checked') === 'true'
                : field === 'order' ? Number(control.value) : control.value;
        };
        const write = (row, field, value) => {
            const control = controlFor(row, field);
            if (field === 'main' || field === 'footer') {
                control.setAttribute('aria-checked', value ? 'true' : 'false');
                control.classList.toggle('is-on', Boolean(value));
                control.classList.toggle('is-off', !value);
                control.querySelector('[data-public-nav-toggle-state]').textContent = value ? 'ON' : 'OFF';
            } else {
                control.value = String(value);
            }
        };
        for (const row of rows) {
            for (const field of fields) saved.set(keyFor(row, field), read(row, field));
        }

        const refresh = () => {
            const query = search.value.trim().toLocaleLowerCase('pl');
            const mode = filter.value;
            let matches = 0;
            let mainCount = 0;
            let footerCount = 0;
            for (const row of rows) {
                const main = read(row, 'main');
                const footer = read(row, 'footer');
                if (main) mainCount++;
                if (footer) footerCount++;
                const haystack = ((row.getAttribute('data-public-nav-default') || '') + ' ' + read(row, 'label')).toLocaleLowerCase('pl');
                const visible = main || footer;
                const allowed = mode === 'all'
                    || (mode === 'visible' && visible)
                    || (mode === 'hidden' && !visible)
                    || (mode === 'main' && main)
                    || (mode === 'footer' && footer);
                const show = haystack.includes(query) && allowed;
                row.hidden = !show;
                if (show) matches++;
            }
            counter.textContent = matches + ' z ' + rows.length;
            if (summary) summary.textContent = rows.length + ' linków · menu: ' + mainCount + ' · stopka: ' + footerCount;
            empty.hidden = matches !== 0;
            const visibleRows = rows.filter(row => !row.hidden);
            for (const row of rows) {
                for (const button of row.querySelectorAll('[data-public-nav-move]')) {
                    const index = visibleRows.indexOf(row);
                    button.disabled = reordering || saving || pending.size > 0 || timers.size > 0
                        || index < 0 || (button.dataset.publicNavMove === 'up'
                            ? index === 0 : index === visibleRows.length - 1);
                }
            }
        };
        const setStatus = (message, isError = false) => {
            status.textContent = message;
            status.classList.toggle('is-error', isError);
        };

        const drain = async () => {
            if (saving) return;
            saving = true;
            while (pending.size !== 0) {
                const [key, { row, field }] = pending.entries().next().value;
                pending.delete(key);
                const value = read(row, field);
                if (value === saved.get(key)) continue;
                const control = controlFor(row, field);
                if (!control.checkValidity()) {
                    write(row, field, saved.get(key));
                    setStatus('Nieprawidłowa wartość. Zmiana nie została zapisana.', true);
                    refresh();
                    continue;
                }
                control.setAttribute('aria-busy', 'true');
                setStatus('Zapisywanie…');
                try {
                    const body = new URLSearchParams();
                    body.set('_token', form.querySelector('input[name="_token"]').value);
                    body.set('link_id', row.dataset.publicNavId);
                    body.set('field', field);
                    body.set('value', typeof value === 'boolean' ? (value ? '1' : '0') : String(value));
                    const response = await fetch(form.action, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        body,
                    });
                    const data = await response.json().catch(() => null);
                    if (!response.ok || data?.ok !== true || data.field !== field || data.id !== row.dataset.publicNavId) {
                        throw new Error(typeof data?.message === 'string' ? data.message : 'Nie udało się zapisać zmiany.');
                    }
                    saved.set(key, data.value);
                    if (read(row, field) === value) write(row, field, data.value);
                    else pending.set(key, { row, field });
                    setStatus('Zapisano.');
                } catch (error) {
                    // A rejected change must never leave an optimistic ON/OFF state on screen.
                    pending.delete(key);
                    write(row, field, saved.get(key));
                    setStatus(error instanceof Error ? error.message : 'Błąd połączenia. Spróbuj ponownie.', true);
                } finally {
                    control.removeAttribute('aria-busy');
                    refresh();
                }
            }
            saving = false;
            refresh();
        };

        const queue = (row, field) => {
            const key = keyFor(row, field);
            if (read(row, field) !== saved.get(key)) pending.set(key, { row, field });
            else pending.delete(key);
            void drain();
        };
        const debounced = (row, field) => {
            const key = keyFor(row, field);
            clearTimeout(timers.get(key));
            timers.set(key, setTimeout(() => {
                timers.delete(key);
                queue(row, field);
            }, 650));
            refresh();
        };
        const flush = (row, field) => {
            const key = keyFor(row, field);
            clearTimeout(timers.get(key));
            timers.delete(key);
            queue(row, field);
        };

        form.addEventListener('click', async event => {
            const button = event.target.closest('[data-public-nav-move]');
            if (!button || button.disabled || reordering || saving) return;
            const row = button.closest('[data-public-nav-row]');
            const visible = rows.filter(item => !item.hidden);
            const neighbor = visible[visible.indexOf(row) + (button.dataset.publicNavMove === 'up' ? -1 : 1)];
            if (!neighbor) return;
            const next = rows.filter(item => item !== row);
            next.splice(next.indexOf(neighbor) + (button.dataset.publicNavMove === 'down' ? 1 : 0), 0, row);
            reordering = true;
            // Keep other edits out of this transaction; restore controls after completion.
            const controls = Array.from(form.querySelectorAll('input, button'));
            const disabled = controls.map(control => control.disabled);
            controls.forEach(control => { control.disabled = true; });
            refresh();
            setStatus('Zapisywanie kolejności…');
            try {
                const ids = next.map(item => item.dataset.publicNavId);
                const body = new URLSearchParams({
                    _token: form.querySelector('input[name="_token"]').value,
                    field: 'reorder',
                    value: JSON.stringify(ids),
                });
                const response = await fetch(form.action, {
                    method: 'POST', credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body,
                });
                const data = await response.json();
                if (!response.ok || data?.ok !== true || JSON.stringify(data.ids) !== JSON.stringify(ids)) {
                    throw new Error(data?.message || 'Nie udało się zapisać kolejności.');
                }
                rows = next;
                const tbody = row.parentElement;
                rows.forEach((item, index) => {
                    tbody.append(item);
                    write(item, 'order', index);
                    saved.set(keyFor(item, 'order'), index);
                });
                setStatus('Zapisano kolejność.');
            } catch (error) {
                setStatus(error instanceof Error ? error.message : 'Błąd połączenia. Spróbuj ponownie.', true);
            } finally {
                reordering = false;
                controls.forEach((control, index) => { control.disabled = disabled[index]; });
                refresh();
                button.focus();
            }
        });

        // There is intentionally no bulk submit button or page reload.
        form.addEventListener('submit', event => event.preventDefault());
        search.addEventListener('input', refresh);
        filter.addEventListener('change', refresh);
        for (const row of rows) {
            for (const field of ['main', 'footer']) {
                controlFor(row, field).addEventListener('click', () => {
                    write(row, field, !read(row, field));
                    refresh();
                    queue(row, field);
                });
            }
            const label = controlFor(row, 'label');
            label.addEventListener('input', () => { refresh(); debounced(row, 'label'); });
            label.addEventListener('blur', () => flush(row, 'label'));
            const order = controlFor(row, 'order');
            order.addEventListener('change', () => flush(row, 'order'));
            order.addEventListener('blur', () => flush(row, 'order'));
        }
        const focusRequestedLink = () => {
            const target = rows.find(row => '#' + row.id === window.location.hash);
            if (!target) return;
            search.value = '';
            filter.value = 'all';
            refresh();
            requestAnimationFrame(() => {
                target.scrollIntoView({ block: 'center' });
                controlFor(target, 'label')?.focus({ preventScroll: true });
            });
        };
        window.addEventListener('hashchange', focusRequestedLink);
        focusRequestedLink();
        refresh();
    }
})();
