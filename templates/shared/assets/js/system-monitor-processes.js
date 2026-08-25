(() => {
    'use strict';

    const root = document.querySelector('[data-system-monitor]');
    if (!root) return;

    const endpoint = 'index.php?route=/admin/system-monitor/processes';
    const interval = 10000;
    let timer = 0;
    let stopped = false;

    const number = (value) => Number.isFinite(Number(value)) ? Number(value) : null;
    const formatBytes = (value) => {
        const bytes = number(value);
        if (bytes === null || bytes < 0) return '—';
        const units = ['B', 'KiB', 'MiB', 'GiB', 'TiB'];
        let scaled = bytes;
        let unit = 0;
        while (scaled >= 1024 && unit < units.length - 1) {
            scaled /= 1024;
            unit++;
        }
        const decimals = unit === 0 ? 0 : (scaled >= 100 ? 0 : (scaled >= 10 ? 1 : 2));
        return `${scaled.toFixed(decimals)} ${units[unit]}`;
    };
    const formatPercent = (value) => {
        const percent = number(value);
        return percent === null ? '—' : `${Math.max(0, percent).toFixed(1)}%`;
    };
    const element = (tag, className = '', text = '') => {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== '') node.textContent = text;
        return node;
    };
    const table = (headers) => {
        const wrapper = element('div', 'table-responsive');
        const node = element('table', 'table table-sm align-middle mb-0');
        const thead = document.createElement('thead');
        const headRow = document.createElement('tr');
        headers.forEach((header) => headRow.append(element('th', '', header)));
        thead.append(headRow);
        const body = document.createElement('tbody');
        node.append(thead, body);
        wrapper.append(node);
        return { wrapper, body };
    };

    const renderGroups = (groups) => {
        const block = element('div');
        block.append(element('h4', 'h6 mb-2', 'Grupy / usługi'));
        const grid = table(['Proces / unit', 'Liczba', 'RAM', 'Udział']);
        (Array.isArray(groups) ? groups : []).slice(0, 20).forEach((group) => {
            const row = document.createElement('tr');
            const label = String(group?.label || group?.name || '—');
            const name = String(group?.name || '');
            const labelCell = element('td', 'text-break');
            labelCell.append(element('strong', '', label));
            if (group?.unit && name && name !== label) {
                labelCell.append(element('small', 'text-secondary d-block', name));
            }
            row.append(labelCell);
            row.append(element('td', '', String(Math.max(0, Math.round(number(group?.processes) ?? 0)))));
            row.append(element('td', '', formatBytes(group?.memory_bytes)));
            row.append(element('td', '', formatPercent(group?.memory_percent)));
            grid.body.append(row);
        });
        block.append(grid.wrapper);
        return block;
    };

    const renderProcesses = (processes) => {
        const block = element('div');
        block.append(element('h4', 'h6 mb-2', 'Najcięższe PID-y'));
        const grid = table(['PID', 'Proces', 'Użytkownik', 'Unit', 'RAM', 'Udział']);
        (Array.isArray(processes) ? processes : []).slice(0, 20).forEach((process) => {
            const row = document.createElement('tr');
            row.append(element('td', '', String(Math.max(0, Math.round(number(process?.pid) ?? 0)))));
            row.append(element('td', '', String(process?.name || '—')));
            row.append(element('td', '', String(process?.user || '—')));
            row.append(element('td', 'text-break', String(process?.unit || '—')));
            row.append(element('td', '', formatBytes(process?.memory_bytes)));
            row.append(element('td', '', formatPercent(process?.memory_percent)));
            grid.body.append(row);
        });
        block.append(grid.wrapper);
        return block;
    };

    const render = (payload) => {
        root.querySelector('[data-monitor-process-memory]')?.remove();
        const snapshot = payload?.processes || {};
        const wrapper = element('div', 'mt-4');
        wrapper.dataset.monitorProcessMemory = 'true';
        const section = element('section', 'admin-panel p-3');

        const heading = element('div', 'd-flex flex-wrap justify-content-between align-items-center gap-2 mb-3');
        heading.append(element('h3', 'h5 mb-0', 'Zużycie RAM przez procesy'));
        const sampledAt = number(snapshot?.sampled_at);
        heading.append(element(
            'small',
            'text-secondary',
            sampledAt === null
                ? 'odświeżanie co 10 s'
                : `${new Date(sampledAt * 1000).toLocaleTimeString('pl-PL')} · odświeżanie co 10 s`
        ));
        section.append(heading);

        const scanned = Math.max(0, Math.round(number(snapshot?.scanned_processes) ?? 0));
        const readable = Math.max(0, Math.round(number(snapshot?.readable_processes) ?? 0));
        section.append(element(
            'p',
            'text-secondary small mb-2',
            `Zakres: wszystkie procesy widoczne w /proc, niezależnie od właściciela. `
                + `RAM hosta: ${formatBytes(snapshot?.total_memory_bytes)} · odczytano ${readable} z ${scanned} procesów.`
        ));
        section.append(element(
            'p',
            'text-secondary small mb-3',
            'Kolumna „Użytkownik” pokazuje właściciela każdego PID-u, np. debian, www-data, root, mysql lub postgres.'
        ));
        if (readable < scanned) {
            section.append(element(
                'div',
                'alert alert-warning py-2 small mb-3',
                `Nie wszystkie procesy są czytelne dla procesu miniPORTAL (${readable}/${scanned}). `
                    + 'Może to wynikać z ustawień /proc (np. hidepid), kontenera lub dodatkowych ograniczeń systemowych.'
            ));
        }

        const columns = element('div', 'admin-panel-grid admin-panel-grid-balanced');
        const left = element('div', 'admin-panel-column');
        const right = element('div', 'admin-panel-column');
        left.append(renderGroups(snapshot?.groups));
        right.append(renderProcesses(snapshot?.processes));
        columns.append(left, right);
        section.append(columns);

        if (snapshot?.note) {
            section.append(element('p', 'text-secondary small mt-3 mb-0', String(snapshot.note)));
        }
        wrapper.append(section);

        const cleaner = root.querySelector('[data-monitor-log-cleaner]');
        if (cleaner) {
            root.insertBefore(wrapper, cleaner);
        } else {
            root.append(wrapper);
        }
    };

    const schedule = () => {
        window.clearTimeout(timer);
        if (!stopped) timer = window.setTimeout(load, interval);
    };

    const load = async () => {
        if (stopped) return;
        if (document.hidden) {
            schedule();
            return;
        }
        try {
            const response = await fetch(endpoint, {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { Accept: 'application/json' },
            });
            if (response.status === 401 || response.status === 403) {
                stopped = true;
                return;
            }
            if (!response.ok) return;
            const payload = await response.json();
            if (payload?.ok === true) render(payload);
        } catch (_) {
            // Ranking procesów jest dodatkiem i nie zatrzymuje głównego monitora.
        } finally {
            schedule();
        }
    };

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden && !stopped) {
            window.clearTimeout(timer);
            void load();
        }
    });

    void load();
})();
