(() => {
    'use strict';
    const root = document.querySelector('[data-system-monitor]');
    if (!root) return;
    const section = document.createElement('section');
    section.className = 'admin-panel p-3 mb-4';
    section.dataset.monitorActions = '';
    root.prepend(section);
    const node = (tag, text, className = '') => {
        const el = document.createElement(tag);
        el.textContent = text;
        el.className = className;
        return el;
    };
    let timer;
    let busy = false;
    let stopped = false;
    const heading = () => section.append(node('h3', 'GitHub Actions', 'h5'));
    const message = (text) => {
        section.replaceChildren();
        heading();
        section.append(node('p', text, 'text-secondary mb-0'));
    };
    message('Łączenie z runnerami…');
    const labels = { busy: 'Wykonuje zadanie', idle: 'Gotowy', offline: 'Wyłączony', connecting: 'Łączenie' };
    const results = { Succeeded: 'Sukces', Failed: 'Błąd', Canceled: 'Anulowano', Skipped: 'Pominięto' };
    const render = (data) => {
        if (!data.available) return message('Podgląd runnerów nie jest skonfigurowany na tym serwerze.');
        section.replaceChildren();
        heading();
        section.append(node('p', data.stale ? 'Dane nieaktualne — kolektor nie odpowiada.' :
            `Odświeżanie co 5 s · Pomiar ${new Date(data.sampled_at * 1000).toLocaleTimeString('pl-PL')}`, 'text-secondary d-block'));
        const grid = node('div', '', 'row row-cols-1 row-cols-lg-3 g-3');
        for (const runner of data.runners) {
            const column = node('div', '', 'col');
            const card = node('article', '', 'metric-card h-100');
            card.append(node('strong', runner.name, 'text-break d-block mb-2'));
            card.append(node('span', data.stale ? 'Stan nieznany' : (labels[runner.state] || 'Stan nieznany'), 'metric-label d-block mb-2'));
            card.append(node('span', runner.job || 'Brak bieżącego zadania', 'text-break d-block mb-2'));
            if (runner.started_at) {
                const seconds = Math.max(0, Math.floor(Date.now() / 1000 - runner.started_at));
                card.append(node('small', `Czas zadania: ${Math.floor(seconds / 60)} min ${seconds % 60} s`, 'text-secondary d-block'));
            }
            if (runner.last_result) card.append(node('small', `Ostatni wynik: ${results[runner.last_result] || '—'}`, 'text-secondary d-block'));
            column.append(card);
            grid.append(column);
        }
        if (!data.runners.length) grid.append(node('p', 'Brak lokalnych runnerów.'));
        section.append(grid);
    };
    const load = async () => {
        if (busy || stopped || document.hidden || !root.isConnected) return;
        busy = true;
        clearTimeout(timer);
        try {
            const response = await fetch('index.php?route=/admin/system-monitor/actions', {
                credentials: 'same-origin', cache: 'no-store', signal: AbortSignal.timeout(8000),
                headers: { Accept: 'application/json' }
            });
            if (response.status === 401 || response.status === 403) stopped = true;
            if (!response.ok) throw new Error('Unavailable');
            const payload = await response.json();
            if (!payload.ok) throw new Error('Unavailable');
            render(payload.actions);
        } catch (_) {
            message('Nie udało się odświeżyć stanu runnerów.');
        } finally {
            busy = false;
            if (!stopped && root.isConnected) timer = setTimeout(load, 5000);
        }
    };
    document.addEventListener('visibilitychange', () => {
        clearTimeout(timer);
        if (!document.hidden) void load();
    });
    void load();
})();
