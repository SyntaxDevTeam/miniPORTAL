(() => {
    'use strict';

    const root = document.querySelector('[data-system-monitor]');
    if (!root) return;

    const endpoint = root.dataset.endpoint || 'index.php?route=/admin/system-monitor/metrics';
    const infrastructureEndpoint = 'index.php?route=/admin/system-monitor/infrastructure';
    const cleanerEndpoint = 'index.php?route=/admin/system-monitor/log-cleaner';
    const cleanerJobEndpoint = 'index.php?route=/admin/system-monitor/log-cleaner/job';
    const interval = Math.max(1000, Number.parseInt(root.dataset.interval || '2000', 10) || 2000);
    const status = root.querySelector('[data-monitor-status]');
    const updated = root.querySelector('[data-monitor-updated]');
    let previous = null;
    let timer = 0;
    let stopped = false;
    let cleanerCsrf = '';

    const number = (value) => Number.isFinite(Number(value)) ? Number(value) : null;
    const percent = (used, total) => {
        const safeUsed = number(used);
        const safeTotal = number(total);
        return safeUsed === null || safeTotal === null || safeTotal <= 0
            ? null
            : Math.max(0, Math.min(100, safeUsed / safeTotal * 100));
    };
    const formatPercent = (value) => value === null ? '—' : `${value.toFixed(1)}%`;
    const formatBytes = (value) => {
        const bytes = number(value);
        if (bytes === null || bytes < 0) return '—';
        const units = ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB'];
        let scaled = bytes;
        let unit = 0;
        while (scaled >= 1024 && unit < units.length - 1) {
            scaled /= 1024;
            unit++;
        }
        const decimals = unit === 0 ? 0 : (scaled >= 100 ? 0 : (scaled >= 10 ? 1 : 2));
        return `${scaled.toFixed(decimals)} ${units[unit]}`;
    };
    const formatRate = (value) => value === null ? '—' : `${formatBytes(Math.max(0, value))}/s`;
    const formatDuration = (value) => {
        const seconds = number(value);
        if (seconds === null || seconds < 0) return '—';
        const whole = Math.floor(seconds);
        const days = Math.floor(whole / 86400);
        const hours = Math.floor((whole % 86400) / 3600);
        const minutes = Math.floor((whole % 3600) / 60);
        if (days > 0) return `${days} d ${hours} h ${minutes} min`;
        if (hours > 0) return `${hours} h ${minutes} min`;
        return `${minutes} min`;
    };
    const formatDate = (value) => {
        const timestamp = number(value);
        return timestamp === null ? '—' : new Date(timestamp * 1000).toLocaleString('pl-PL');
    };

    const setStatus = (label, variant) => {
        if (!status) return;
        status.textContent = label;
        status.className = `badge text-bg-${variant}`;
    };
    const setMetric = (key, value, detail) => {
        const card = root.querySelector(`[data-monitor-metric="${key}"]`);
        if (!card) return;
        const valueNode = card.querySelector('[data-monitor-value]');
        const detailNode = card.querySelector('[data-monitor-detail]');
        if (valueNode) valueNode.textContent = value;
        if (detailNode) detailNode.textContent = detail;
    };
    const setResource = (key, value) => {
        const container = root.querySelector(`[data-monitor-resource="${key}"]`);
        if (!container) return;
        const label = container.querySelector('[data-monitor-resource-label]');
        const progress = container.querySelector('[data-monitor-progress]');
        const normalized = value === null ? 0 : Math.max(0, Math.min(100, value));
        if (label) label.textContent = value === null ? 'Niedostępne' : `${value.toFixed(1)}% użycia`;
        if (progress instanceof HTMLProgressElement) {
            progress.value = normalized;
            progress.textContent = `${normalized.toFixed(1)}%`;
        }
    };

    const facts = new Map();
    root.querySelectorAll('[data-monitor-fact-map]').forEach((marker) => {
        const keys = (marker.dataset.monitorFactMap || '').split(',').map((key) => key.trim()).filter(Boolean);
        const grid = marker.previousElementSibling;
        if (!grid) return;
        grid.querySelectorAll('.profile-fact').forEach((fact, index) => {
            if (keys[index]) facts.set(keys[index], fact.querySelector('strong'));
        });
    });
    const setFact = (key, value) => {
        const node = facts.get(key);
        if (node) node.textContent = value;
    };

    const cpuUsage = (payload) => {
        const total = number(payload?.cpu?.total_ticks);
        const idle = number(payload?.cpu?.idle_ticks);
        const oldTotal = number(previous?.cpu?.total_ticks);
        const oldIdle = number(previous?.cpu?.idle_ticks);
        if (total === null || idle === null || oldTotal === null || oldIdle === null) return null;
        const deltaTotal = total - oldTotal;
        const deltaIdle = idle - oldIdle;
        if (deltaTotal <= 0 || deltaIdle < 0) return null;
        return Math.max(0, Math.min(100, (1 - deltaIdle / deltaTotal) * 100));
    };
    const networkRates = (payload) => {
        const now = number(payload?.sampled_at);
        const before = number(previous?.sampled_at);
        const rx = number(payload?.network?.rx_bytes);
        const tx = number(payload?.network?.tx_bytes);
        const oldRx = number(previous?.network?.rx_bytes);
        const oldTx = number(previous?.network?.tx_bytes);
        const elapsed = now !== null && before !== null ? now - before : null;
        if (elapsed === null || elapsed <= 0 || rx === null || tx === null || oldRx === null || oldTx === null) {
            return { rx: null, tx: null };
        }
        return {
            rx: Math.max(0, rx - oldRx) / elapsed,
            tx: Math.max(0, tx - oldTx) / elapsed,
        };
    };

    const render = (payload) => {
        const cpu = cpuUsage(payload);
        const memoryUsed = percent(payload?.memory?.used, payload?.memory?.total);
        const diskUsed = percent(payload?.disk?.used, payload?.disk?.total);
        const swapTotal = number(payload?.memory?.swap_total);
        const swapUsed = swapTotal !== null && swapTotal > 0 ? percent(payload?.memory?.swap_used, swapTotal) : null;
        const rates = networkRates(payload);
        const cores = number(payload?.cpu?.cores);

        setMetric('cpu', cpu === null ? 'Zbieranie…' : formatPercent(cpu), cpu === null
            ? 'Potrzebne są dwa kolejne pomiary.'
            : `${formatPercent(100 - cpu)} wolne · ${cores === null ? '—' : Math.round(cores)} CPU`);
        setMetric('memory', `${formatBytes(payload?.memory?.used)} / ${formatBytes(payload?.memory?.total)}`,
            `Dostępne: ${formatBytes(payload?.memory?.available)}${memoryUsed === null ? '' : ` · ${formatPercent(memoryUsed)} użycia`}`);
        setMetric('disk', `${formatBytes(payload?.disk?.used)} / ${formatBytes(payload?.disk?.total)}`,
            `Wolne: ${formatBytes(payload?.disk?.free)}${diskUsed === null ? '' : ` · ${formatPercent(diskUsed)} użycia`}`);
        if (swapTotal === 0) {
            setMetric('swap', 'Wyłączony', 'System nie ma skonfigurowanego swapu.');
        } else {
            setMetric('swap', `${formatBytes(payload?.memory?.swap_used)} / ${formatBytes(payload?.memory?.swap_total)}`,
                `Wolne: ${formatBytes(payload?.memory?.swap_free)}${swapUsed === null ? '' : ` · ${formatPercent(swapUsed)} użycia`}`);
        }

        setResource('cpu', cpu);
        setResource('memory', memoryUsed);
        setResource('disk', diskUsed);
        setResource('swap', swapTotal === 0 ? 0 : swapUsed);

        const load = Array.isArray(payload?.cpu?.load)
            ? payload.cpu.load.slice(0, 3).map((value) => Number(value).toFixed(2)).join(' / ')
            : '—';
        setFact('host', String(payload?.host?.name || '—'));
        setFact('uptime', formatDuration(payload?.host?.uptime_seconds));
        setFact('cpu-cores', cores === null ? '—' : String(Math.round(cores)));
        setFact('load-average', load || '—');
        setFact('network-rx-rate', formatRate(rates.rx));
        setFact('network-tx-rate', formatRate(rates.tx));
        setFact('network-rx-total', formatBytes(payload?.network?.rx_bytes));
        setFact('network-tx-total', formatBytes(payload?.network?.tx_bytes));

        const sampledAt = number(payload?.sampled_at);
        if (updated) {
            updated.textContent = sampledAt === null
                ? 'Odebrano pomiar.'
                : `Ostatni pomiar: ${new Date(sampledAt * 1000).toLocaleTimeString('pl-PL')}`;
        }
        const partial = payload?.platform?.procfs === false;
        setStatus(partial ? 'Dane częściowe' : 'Online', partial ? 'warning' : 'success');
        previous = payload;
    };

    const element = (tag, className = '', text = '') => {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== '') node.textContent = text;
        return node;
    };

    const panel = (title, meta = '') => {
        const section = element('section', 'admin-panel p-3');
        const heading = element('div', 'd-flex justify-content-between align-items-center gap-3 mb-3');
        heading.append(element('h3', 'h5 mb-0', title));
        if (meta) heading.append(element('small', 'text-secondary', meta));
        section.append(heading);
        return section;
    };

    const table = (headers) => {
        const wrapper = element('div', 'table-responsive');
        const node = element('table', 'table table-sm align-middle mb-0');
        const thead = document.createElement('thead');
        const row = document.createElement('tr');
        headers.forEach((header) => row.append(element('th', '', header)));
        thead.append(row);
        node.append(thead, document.createElement('tbody'));
        wrapper.append(node);
        return { wrapper, body: node.querySelector('tbody') };
    };

    const renderServices = (services) => {
        const section = panel('Usługi VPS', 'procesy / RAM');
        const grid = table(['Usługa', 'Stan', 'Procesy', 'RAM']);
        (Array.isArray(services) ? services : []).forEach((service) => {
            const row = document.createElement('tr');
            row.append(element('td', '', String(service?.label || '—')));
            const state = element('td');
            state.append(element(
                'span',
                `badge text-bg-${service?.running === true ? 'success' : 'secondary'}`,
                service?.running === true ? 'Działa' : 'Nie wykryto'
            ));
            row.append(state);
            row.append(element('td', '', String(number(service?.processes) ?? 0)));
            row.append(element('td', '', formatBytes(service?.memory_bytes)));
            grid.body?.append(row);
        });
        section.append(grid.wrapper);
        return section;
    };

    const renderDatabaseEngine = (engine) => {
        const block = element('div', 'mb-4');
        const label = String(engine?.label || 'Baza danych');
        block.append(element('h4', 'h6 mb-1', label));
        if (engine?.available !== true) {
            block.append(element('p', 'text-secondary small mb-0', String(engine?.error || 'Dane niedostępne.')));
            return block;
        }

        const connections = number(engine?.connections);
        block.append(element(
            'p',
            'text-secondary small mb-2',
            `${number(engine?.count) ?? 0} baz · ${formatBytes(engine?.total_bytes)}`
                + (connections === null ? '' : ` · ${Math.round(connections)} połączeń`)
        ));
        const grid = table(['Baza', 'Rozmiar']);
        (Array.isArray(engine?.databases) ? engine.databases : []).forEach((database) => {
            const row = document.createElement('tr');
            row.append(element('td', '', String(database?.name || '—')));
            row.append(element('td', '', formatBytes(database?.bytes)));
            grid.body?.append(row);
        });
        block.append(grid.wrapper);
        return block;
    };

    const renderDatabases = (databases) => {
        const section = panel('Bazy danych', 'rozmiar logiczny');
        section.append(renderDatabaseEngine(databases?.mysql));
        section.append(renderDatabaseEngine(databases?.postgresql));
        return section;
    };

    const renderStorage = (storage) => {
        const section = panel('Co zajmuje dysk', `${formatBytes(storage?.used_bytes)} / ${formatBytes(storage?.total_bytes)}`);
        const used = number(storage?.used_bytes) ?? 0;
        const categories = Array.isArray(storage?.categories) ? storage.categories : [];
        categories.forEach((category) => {
            const bytes = Math.max(0, number(category?.bytes) ?? 0);
            const share = used > 0 ? Math.max(0, Math.min(100, bytes / used * 100)) : 0;
            const row = element('div', 'mb-3');
            const header = element('div', 'd-flex justify-content-between gap-3 mb-1');
            const label = String(category?.label || 'Inne');
            header.append(element('span', '', label));
            header.append(element('strong', '', `${formatBytes(bytes)} · ${share.toFixed(1)}%`));
            const progress = document.createElement('progress');
            progress.className = 'w-100';
            progress.max = 100;
            progress.value = share;
            progress.textContent = `${share.toFixed(1)}%`;
            row.append(header, progress);
            if (category?.complete === false && category?.source === 'filesystem') {
                row.append(element('small', 'text-secondary', 'Pomiar częściowy — limit czasu lub liczby plików.'));
            } else if (category?.source === 'database') {
                row.append(element('small', 'text-secondary', 'Wartość logiczna pobrana z silnika bazy danych.'));
            } else if (category?.source === 'unavailable') {
                row.append(element('small', 'text-secondary', 'Katalog niedostępny dla użytkownika procesu WWW.'));
            }
            section.append(row);
        });
        return section;
    };

    const renderInfrastructure = (payload) => {
        root.querySelector('[data-monitor-infrastructure]')?.remove();
        const infrastructure = payload?.infrastructure || {};
        const wrapper = element('div', 'mt-4');
        wrapper.dataset.monitorInfrastructure = 'true';

        const titleRow = element('div', 'd-flex flex-wrap justify-content-between align-items-center gap-2 mb-3');
        titleRow.append(element('h2', 'h4 mb-0', 'Infrastruktura VPS'));
        const sampledAt = number(infrastructure?.sampled_at);
        titleRow.append(element(
            'small',
            'text-secondary',
            sampledAt === null
                ? 'Dane cache’owane.'
                : `Stan z ${new Date(sampledAt * 1000).toLocaleTimeString('pl-PL')} · cache ${number(infrastructure?.cache_ttl_seconds) ?? 600} s`
        ));
        wrapper.append(titleRow);

        const grid = element('div', 'admin-panel-grid admin-panel-grid-balanced');
        const left = element('div', 'admin-panel-column');
        const right = element('div', 'admin-panel-column');
        left.append(renderServices(infrastructure?.services), renderDatabases(infrastructure?.databases));
        right.append(renderStorage(infrastructure?.storage));
        grid.append(left, right);
        wrapper.append(grid);
        root.append(wrapper);
    };

    const loadInfrastructure = async () => {
        try {
            const response = await fetch(infrastructureEndpoint, {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) return;
            const payload = await response.json();
            if (payload?.ok === true) renderInfrastructure(payload);
        } catch (_) {
            // Dane infrastruktury są dodatkiem; awaria nie zatrzymuje monitora live.
        }
    };

    const sleep = (milliseconds) => new Promise((resolve) => window.setTimeout(resolve, milliseconds));

    const waitForCleanerJob = async (jobId) => {
        for (let attempt = 0; attempt < 60; attempt++) {
            const response = await fetch(`${cleanerJobEndpoint}&job_id=${encodeURIComponent(jobId)}`, {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { Accept: 'application/json' },
            });
            const payload = await response.json().catch(() => null);
            if (!response.ok || payload?.ok !== true) {
                throw new Error(String(payload?.message || `HTTP ${response.status}`));
            }

            const job = payload?.job || {};
            const jobStatus = String(job?.status || '');
            if (jobStatus === 'succeeded') return job;
            if (jobStatus === 'failed') {
                throw new Error(String(job?.error || 'Node Agent zgłosił błąd podczas czyszczenia logów.'));
            }
            if (!['queued', 'running'].includes(jobStatus)) {
                throw new Error('Node Agent zwrócił nieprawidłowy stan zadania.');
            }
            await sleep(1000);
        }

        throw new Error('Zadanie agenta nadal trwa. Jego stan można sprawdzić w Node Managerze.');
    };

    const renderCleaner = (payload) => {
        root.querySelector('[data-monitor-log-cleaner]')?.remove();
        const cleaner = payload?.cleaner || {};
        cleanerCsrf = String(payload?.csrf_token || cleanerCsrf || '');
        const retention = Math.max(1, Math.round(number(cleaner?.retention_days) ?? 14));
        const reclaimable = Math.max(0, number(cleaner?.reclaimable_bytes) ?? 0);
        const protectedBytes = Math.max(0, number(cleaner?.protected_bytes) ?? 0);
        const candidateFiles = Math.max(0, Math.round(number(cleaner?.candidate_files) ?? 0));

        const wrapper = element('div', 'mt-4');
        wrapper.dataset.monitorLogCleaner = 'true';
        const section = panel('Cleaner logów', 'uprzywilejowany Node Agent');

        if (cleaner?.available !== true) {
            section.append(element(
                'div',
                'alert alert-warning mb-3',
                String(cleaner?.error || 'Node Agent nie udostępnia obsługi logów systemowych.')
            ));
            section.append(element(
                'p',
                'text-secondary small mb-0',
                'miniPORTAL nie próbuje usuwać plików jako www-data. Cleaner będzie aktywny dopiero wtedy, gdy lokalny Node Agent obsługuje kontrakt /v1/system/logs oraz zadanie system.logs.clean.'
            ));
            wrapper.append(section);
            root.append(wrapper);
            return;
        }

        const notice = element(
            'div',
            `alert ${reclaimable > 0 ? 'alert-warning' : 'alert-info'} mb-3`,
            reclaimable > 0
                ? `Node Agent może odzyskać około ${formatBytes(reclaimable)} z ${candidateFiles} starych plików logów.`
                : 'Brak logów spełniających aktualną politykę retencji.'
        );
        section.append(notice);

        const journalReclaimable = number(cleaner?.journal_reclaimable_bytes);
        const journalBytes = number(cleaner?.journal_bytes);
        if (journalBytes !== null) {
            section.append(element(
                'p',
                'text-secondary small mb-3',
                `systemd-journal: ${formatBytes(journalBytes)}`
                    + (journalReclaimable === null ? '' : ` · możliwe do odzyskania ${formatBytes(journalReclaimable)}`)
            ));
        }

        const controls = element('div', 'd-flex flex-wrap align-items-end gap-3 mb-3');
        const selectWrap = element('div');
        selectWrap.append(element('label', 'form-label small mb-1', 'Retencja'));
        const select = document.createElement('select');
        select.className = 'form-select form-select-sm';
        select.setAttribute('aria-label', 'Retencja logów');
        [7, 14, 30, 90, 180].forEach((days) => {
            const option = document.createElement('option');
            option.value = String(days);
            option.textContent = `${days} dni`;
            option.selected = days === retention;
            select.append(option);
        });
        if (![7, 14, 30, 90, 180].includes(retention)) {
            const option = document.createElement('option');
            option.value = String(retention);
            option.textContent = `${retention} dni`;
            option.selected = true;
            select.prepend(option);
        }
        selectWrap.append(select);

        const journalWrap = element('div', 'form-check mb-1');
        const journal = document.createElement('input');
        journal.className = 'form-check-input';
        journal.type = 'checkbox';
        journal.id = 'system-monitor-clean-journal';
        journal.checked = true;
        const journalLabel = element('label', 'form-check-label small', 'Uwzględnij systemd-journal');
        journalLabel.htmlFor = journal.id;
        journalWrap.append(journal, journalLabel);

        const refresh = element('button', 'btn btn-sm btn-outline-light', 'Przelicz');
        refresh.type = 'button';
        refresh.addEventListener('click', () => void loadCleaner(Number.parseInt(select.value, 10)));

        const clean = element('button', 'btn btn-sm btn-outline-danger', 'Zleć bezpieczne czyszczenie');
        clean.type = 'button';
        clean.disabled = reclaimable <= 0 || cleanerCsrf === '';
        clean.addEventListener('click', async () => {
            const selectedRetention = Math.max(1, Number.parseInt(select.value, 10) || retention);
            const confirmed = window.confirm(
                `Zlecić Node Agentowi czyszczenie logów starszych niż ${selectedRetention} dni?\n\n`
                + `Szacunkowo możliwe do odzyskania: ${formatBytes(reclaimable)}.\n`
                + `${journal.checked ? 'systemd-journal będzie uwzględniony.' : 'systemd-journal nie będzie czyszczony.'}`
            );
            if (!confirmed) return;

            clean.disabled = true;
            clean.textContent = 'Zlecanie…';
            try {
                const body = new URLSearchParams();
                body.set('_token', cleanerCsrf);
                body.set('retention_days', String(selectedRetention));
                body.set('include_journal', journal.checked ? '1' : '0');
                const response = await fetch(cleanerEndpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                    },
                    body: body.toString(),
                });
                const result = await response.json().catch(() => null);
                if (!response.ok || result?.ok !== true) {
                    throw new Error(String(result?.message || `HTTP ${response.status}`));
                }

                const jobId = String(result?.job?.job_id || '');
                if (!jobId) throw new Error('Node Agent nie zwrócił identyfikatora zadania.');
                notice.className = 'alert alert-info mb-3';
                notice.textContent = `Agent przyjął zadanie ${jobId}. Oczekiwanie na zakończenie…`;
                clean.textContent = 'Czyszczenie…';

                const initialStatus = String(result?.job?.status || '');
                if (initialStatus === 'failed') {
                    throw new Error(String(result?.job?.error || 'Node Agent odrzucił zadanie.'));
                }
                if (initialStatus !== 'succeeded') {
                    await waitForCleanerJob(jobId);
                }

                notice.className = 'alert alert-success mb-3';
                notice.textContent = 'Node Agent zakończył czyszczenie logów. Odświeżam analizę zajętości.';
                await Promise.all([loadCleaner(selectedRetention), loadInfrastructure()]);
            } catch (error) {
                notice.className = 'alert alert-danger mb-3';
                notice.textContent = error instanceof Error ? error.message : 'Nie udało się wykonać czyszczenia przez Node Agenta.';
                clean.disabled = false;
                clean.textContent = 'Zleć bezpieczne czyszczenie';
            }
        });

        controls.append(selectWrap, journalWrap, refresh, clean);
        section.append(controls);

        const summaryParts = [
            `Kandydaci: ${candidateFiles}`,
            `łącznie ${formatBytes(cleaner?.candidate_bytes)}`,
            `możliwe do odzyskania ${formatBytes(reclaimable)}`,
        ];
        if (protectedBytes > 0) summaryParts.push(`chronione ${formatBytes(protectedBytes)}`);
        section.append(element('p', 'text-secondary small mb-3', summaryParts.join(' · ')));

        const candidates = Array.isArray(cleaner?.candidates) ? cleaner.candidates.slice(0, 30) : [];
        if (candidates.length > 0) {
            const grid = table(['Plik / źródło', 'Rozmiar', 'Modyfikacja', 'Stan']);
            candidates.forEach((candidate) => {
                const row = document.createElement('tr');
                row.append(element('td', 'text-break', String(candidate?.path || candidate?.label || '—')));
                row.append(element('td', '', formatBytes(candidate?.bytes)));
                row.append(element('td', '', formatDate(candidate?.modified_at)));
                const state = element('td');
                state.append(element(
                    'span',
                    `badge text-bg-${candidate?.deletable === false ? 'secondary' : 'warning'}`,
                    candidate?.deletable === false ? 'Chroniony' : 'Do czyszczenia'
                ));
                row.append(state);
                grid.body?.append(row);
            });
            section.append(grid.wrapper);
            if (cleaner?.preview_truncated === true || candidateFiles > candidates.length) {
                section.append(element('small', 'text-secondary d-block mt-2', 'Lista pokazuje tylko największą część kandydatów.'));
            }
        }

        section.append(element(
            'p',
            'text-secondary small mt-3 mb-0',
            'Operacja jest wykonywana przez lokalnego Node Agenta po Unix Socket. miniPORTAL nie przekazuje ścieżek ani poleceń powłoki i nie usuwa logów jako użytkownik www-data.'
        ));
        wrapper.append(section);
        root.append(wrapper);
    };

    const loadCleaner = async (retentionDays = null) => {
        try {
            const suffix = retentionDays === null ? '' : `&retention_days=${encodeURIComponent(String(retentionDays))}`;
            const response = await fetch(`${cleanerEndpoint}${suffix}`, {
                method: 'GET',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) return;
            const payload = await response.json();
            if (payload?.ok === true) renderCleaner(payload);
        } catch (_) {
            // Cleaner jest dodatkiem i nie wpływa na podstawowe metryki monitora.
        }
    };

    const schedule = () => {
        window.clearTimeout(timer);
        if (!stopped) timer = window.setTimeout(poll, interval);
    };
    const poll = async () => {
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
                setStatus(response.status === 401 ? 'Sesja wygasła' : 'Brak dostępu', 'danger');
                if (updated) updated.textContent = 'Odśwież stronę po ponownym zalogowaniu.';
                return;
            }
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const payload = await response.json();
            if (!payload || payload.ok !== true) throw new Error('Nieprawidłowa odpowiedź monitora.');
            render(payload);
        } catch (error) {
            setStatus('Brak połączenia', 'danger');
            if (updated) updated.textContent = error instanceof Error ? error.message : 'Nie udało się pobrać metryk.';
        } finally {
            schedule();
        }
    };

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden && !stopped) {
            window.clearTimeout(timer);
            void poll();
        }
    });

    void poll();
    void loadInfrastructure();
    void loadCleaner();
})();
