(() => {
    'use strict';
    if (window.miniportalGroupedRoster) return;
    window.miniportalGroupedRoster = true;
    let pending = false;
    async function refresh() {
        const roster = document.querySelector('[data-grouped-roster]');
        if (!roster || !roster.dataset.groupedRoster || document.hidden || pending || roster.contains(document.activeElement)) return;
        pending = true;
        try {
            const response = await fetch(roster.dataset.groupedRoster, {cache: 'no-store', credentials: 'same-origin', signal: AbortSignal.timeout(10000)});
            if (!response.ok) return;
            const documentFragment = new DOMParser().parseFromString(await response.text(), 'text/html');
            const next = documentFragment.querySelector('[data-grouped-roster]');
            if (next && roster.isConnected && !roster.contains(document.activeElement)) roster.replaceWith(next);
        } catch (_) { /* Last successful roster stays available during an outage. */ }
        finally { pending = false; }
    }
    setInterval(refresh, 30000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
})();
