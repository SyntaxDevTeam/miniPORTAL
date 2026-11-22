(() => {
    'use strict';
    const root = document.querySelector('[data-system-monitor]');
    if (!root) return;
    let timer = 0, stopped = false;
    const element = (tag, css = '', text = '') => {
        const node = document.createElement(tag); node.className = css; node.textContent = text; return node;
    };
    const bytes = value => {
        if (value === null || value === undefined || !Number.isFinite(Number(value))) return '—';
        let size = Number(value), unit = 0; const units = ['B','KiB','MiB','GiB','TiB'];
        while (size >= 1024 && unit < 4) { size /= 1024; unit++; }
        return `${size.toFixed(unit ? 1 : 0)} ${units[unit]}`;
    };
    const percent = value => value === null || value === undefined ? '—' : `${Number(value).toFixed(1)}%`;
    const table = (headers, rows) => {
        const wrapper = element('div','table-responsive');
        const node = element('table','table table-sm align-middle mb-0');
        const head = document.createElement('thead'), tr = document.createElement('tr');
        headers.forEach(label => tr.append(element('th','',label))); head.append(tr);
        const body = document.createElement('tbody'); rows.forEach(row => body.append(row));
        node.append(head,body); wrapper.append(node); return wrapper;
    };
    const row = values => { const tr = document.createElement('tr'); values.forEach(value => tr.append(element('td','text-break',String(value)))); return tr; };
    const terminate = async (button, process, context) => {
        if (!window.confirm(`Zakończyć ${process.name} (PID ${process.pid})? SIGTERM pozwala na łagodne zamknięcie, lecz niezapisana praca procesu może zostać utracona.`)) return;
        button.disabled = true;
        try {
            const response = await fetch('index.php?route=/admin/system-monitor/processes/terminate', {
                method:'POST', credentials:'same-origin', cache:'no-store',
                headers:{'Content-Type':'application/x-www-form-urlencoded','Accept':'application/json'},
                body:new URLSearchParams({_token:context.csrf_token,pid:String(process.pid),start_ticks:String(process.start_ticks),boot_id:String(context.processes.boot_id)})
            });
            const result = await response.json(); window.alert(result.message || 'Operacja odrzucona.');
            if (result.ok) void load();
        } catch { window.alert('Nie można przekazać żądania zakończenia procesu.'); }
        finally { button.disabled = false; }
    };
    const render = context => {
        const data = context.processes || {}, memory = data.memory || {};
        root.querySelector('[data-monitor-process-memory]')?.remove();
        const wrapper = element('div','mt-4'); wrapper.dataset.monitorProcessMemory = 'true';
        const panel = element('section','admin-panel p-3');
        panel.append(element('h3','h5 mb-2','Pamięć i stan procesów'));
        panel.append(element('p','text-secondary small',`Odczytano ${data.readable_processes || 0} z ${data.scanned_processes || 0} procesów. Widok: 20 największych grup i 30 PID-ów. Odświeżanie co 10 s.`));
        if (context.may_terminate && !data.control_available) panel.append(element('p','alert alert-info small','Zamykanie procesów jest niedostępne — usługa sterowania hostem nie jest aktywna.'));
        const summary = element('div','vps-memory-summary');
        for (const [label,value] of [
            ['RAM systemu — jak Dashboard',`${percent(memory.used_percent)} · ${bytes(memory.used)}`],
            ['Dostępne dla nowych aplikacji',bytes(memory.available)],
            ['RSS wszystkich odczytanych procesów',bytes(data.rss_all_bytes)]
        ]) { const item = element('div'); item.append(element('small','',label),element('strong','',value)); summary.append(item); }
        panel.append(summary,element('p','text-secondary small','RAM systemu = MemTotal − MemAvailable. RSS jest inną miarą: współdzielone strony mogą być liczone wielokrotnie, a procesy nie obejmują całej pamięci jądra. Procentów z tabeli nie należy dodawać do procentu systemowego.'));
        const composition = element('details','mb-3');
        composition.append(element('summary','','Skład pamięci fizycznej'),table(['Składnik','Rozmiar'],(memory.components || []).map(part => row([part.label,bytes(part.bytes)]))),element('p','text-secondary small mt-2','MemAvailable obejmuje również część cache możliwą do odzyskania; nie jest dodatkową kategorią do zsumowania z powyższymi.'));
        panel.append(composition);
        if (data.readable_processes < data.scanned_processes) panel.append(element('p','alert alert-warning','Część procesów jest nieczytelna dla kolektora. Zestawienie RSS jest niepełne.'));
        const groups = (data.groups || []).map(group => row([group.label,group.processes,bytes(group.memory_bytes),percent(group.memory_percent)]));
        const groupDetails = element('details','mb-3'); groupDetails.append(element('summary','','Grupy / usługi — RSS'),table(['Proces / unit','Liczba','RAM RSS','Udział RAM'],groups)); panel.append(groupDetails);
        const processes = (data.processes || []).slice(0,30).map(process => {
            const tr = row([process.pid,process.name,process.user,process.state_label || 'Nieznany',process.unit || '—',bytes(process.memory_bytes),percent(process.memory_percent)]);
            const state = tr.children[3]; state.classList.add('vps-process-state');
            if (process.orphaned) state.append(element('small','text-secondary d-block','Rodzic: PID 1 — osierocony, niekoniecznie porzucony'));
            const cell = document.createElement('td');
            if (context.may_terminate && process.can_terminate) {
                const button = element('button','btn btn-sm btn-outline-danger','Zakończ'); button.type = 'button';
                button.addEventListener('click',() => terminate(button,process,context)); cell.append(button);
            } else { const note = element('small','text-secondary',context.may_terminate ? 'Chroniony / niedostępny' : 'Tylko odczyt'); note.title = process.protection || ''; cell.append(note); }
            tr.append(cell); return tr;
        });
        panel.append(table(['PID','Proces','Użytkownik','Stan','Unit','RAM RSS','Udział RAM','Akcja'],processes));
        panel.append(element('p','text-secondary small mt-3 mb-0','Uśpiony proces jest aktywny i zwykle tylko czeka na pracę. Zatrzymany proces nie wykonuje kodu; zombie już zakończył pracę i wymaga obsługi przez rodzica. Sam PPID=1 nie oznacza, że proces jest zbędny.'));
        wrapper.append(panel); const cleaner = root.querySelector('[data-monitor-log-cleaner]');
        if (cleaner) root.insertBefore(wrapper,cleaner); else root.append(wrapper);
    };
    const schedule = () => { clearTimeout(timer); if (!stopped) timer = setTimeout(load,10000); };
    const load = async () => {
        if (stopped) return;
        if (document.hidden) { schedule(); return; }
        try {
            const response = await fetch('index.php?route=/admin/system-monitor/processes',{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}});
            if ([401,403].includes(response.status)) { stopped = true; return; }
            if (response.ok) { const payload = await response.json(); if (payload.ok) render(payload); }
        } catch { /* Other monitor sections remain available. */ }
        finally { schedule(); }
    };
    document.addEventListener('visibilitychange',() => { if (!document.hidden && !stopped) { clearTimeout(timer); void load(); } });
    void load();
})();
