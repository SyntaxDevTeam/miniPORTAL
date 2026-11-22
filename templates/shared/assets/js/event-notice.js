(() => {
    'use strict';
    const storageKey = 'miniportal.event-notice.hidden-until';
    const duration = 48 * 60 * 60 * 1000;
    let hiddenUntil = 0;
    const readDismissal = () => {
        try { hiddenUntil = Number(localStorage.getItem(storageKey)) || 0; } catch (_) {}
        return Number.isFinite(hiddenUntil) && hiddenUntil > Date.now();
    };
    const init = () => document.querySelectorAll('[data-event-notice]').forEach(pin => {
        if (pin.dataset.ready) return;
        pin.dataset.ready = '1';
        const details = pin.querySelector('details');
        const summary = pin.querySelector('summary');
        const consent = document.querySelector('[data-cookie-consent]');
        const lifetime = new AbortController();
        let event = null, request = null, timer = null;
        const visibility = () => {
            const blocked = consent && !consent.hidden && getComputedStyle(consent).display !== 'none';
            const calendar = /^\/(?:admin\/)?events(?:\/|$)/.test(location.pathname);
            const hidden = !event || !!blocked || calendar || readDismissal();
            if (pin.hidden !== hidden) pin.hidden = hidden;
            if (hidden) details.open = false;
        };
        const write = (selector, value) => { pin.querySelector(selector).textContent = value || ''; };
        const refresh = async () => {
            if (!pin.isConnected) { lifetime.abort(); observer?.disconnect(); request?.abort(); clearTimeout(timer); return; }
            if (document.hidden) { clearTimeout(timer); timer = setTimeout(refresh, 60000); return; }
            if (readDismissal()) { visibility(); clearTimeout(timer); timer = setTimeout(refresh, Math.min(60000, hiddenUntil - Date.now())); return; }
            request?.abort(); request = new AbortController();
            try {
                const response = await fetch('/events/notice', {credentials: 'omit', cache: 'no-store', signal: request.signal, headers: {'Accept': 'application/json'}});
                if (!response.ok) throw new Error('Unavailable');
                const data = await response.json();
                const value = data.event;
                if (!value || !['live','upcoming','past'].includes(value.phase) || !/^\/events\/view\?id=[1-9][0-9]*$/.test(value.url)) { event = null; visibility(); return; }
                if (event && event.id !== value.id) details.open = false;
                event = value;
                pin.dataset.phase = value.phase;
                write('[data-event-notice-label]', {live: 'Właśnie trwa', upcoming: 'Już wkrótce', past: 'Ostatnio na serwerze'}[value.phase]);
                write('[data-event-notice-short]', value.title);
                write('[data-event-notice-phase]', value.phase_label);
                write('[data-event-notice-title]', value.title);
                write('[data-event-notice-range]', value.range);
                write('[data-event-notice-summary]', value.summary || 'Poznaj szczegóły wydarzenia, zasady udziału i nagrody.');
                pin.querySelector('[data-event-notice-link]').href = value.url;
                visibility();
            } catch (error) { if (error.name !== 'AbortError') { event = null; visibility(); } }
            finally { clearTimeout(timer); timer = setTimeout(refresh, 60000); }
        };
        const observer = consent ? new MutationObserver(visibility) : null;
        observer?.observe(consent, {attributes: true, attributeFilter: ['hidden','class','style']});
        pin.querySelector('[data-event-notice-close]').addEventListener('click', () => { details.open = false; summary.focus(); }, {signal: lifetime.signal});
        pin.querySelector('[data-event-notice-dismiss]').addEventListener('click', () => {
            hiddenUntil = Date.now() + duration;
            try { localStorage.setItem(storageKey, String(hiddenUntil)); } catch (_) {}
            visibility();
        }, {signal: lifetime.signal});
        window.addEventListener('storage', e => { if (e.key === storageKey || e.key === null) { visibility(); refresh(); } }, {signal: lifetime.signal});
        document.addEventListener('htmx:afterSwap', visibility, {signal: lifetime.signal});
        document.addEventListener('click', e => { if (!pin.contains(e.target)) details.open = false; }, {signal: lifetime.signal});
        document.addEventListener('keydown', e => { if (e.key === 'Escape' && details.open) { details.open = false; summary.focus(); } }, {signal: lifetime.signal});
        document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); }, {signal: lifetime.signal});
        window.addEventListener('pagehide', () => { request?.abort(); clearTimeout(timer); }, {signal: lifetime.signal});
        window.addEventListener('pageshow', e => { if (e.persisted) refresh(); }, {signal: lifetime.signal});
        refresh();
    });
    init();
    document.addEventListener('htmx:afterSwap', init);
})();
