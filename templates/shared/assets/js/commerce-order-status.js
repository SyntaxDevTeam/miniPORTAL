(() => {
    const order = document.querySelector('[data-commerce-status-url]');
    if (!order) return;

    const status = order.querySelector('[data-commerce-status]');
    const detail = order.querySelector('[data-commerce-status-detail]');
    const amount = order.querySelector('[data-commerce-amount-label]');
    const payment = order.querySelector('[data-commerce-payment]');
    const fallback = order.querySelector('[data-commerce-status-fallback]');
    let timer;
    let active = true;
    let running = false;

    const showFallback = (text) => {
        fallback.textContent = text;
        fallback.hidden = !text;
    };

    const schedule = (delay) => {
        clearTimeout(timer);
        if (active && !document.hidden) timer = setTimeout(check, delay);
    };

    async function check() {
        if (!active || running || document.hidden) return;
        running = true;
        let delay = 5000;
        try {
            const response = await fetch(order.dataset.commerceStatusUrl, {
                headers: { Accept: 'application/json' },
                cache: 'no-store',
                signal: AbortSignal.timeout(8000),
            });
            if (response.status === 401 || response.status === 403 || response.status === 404) {
                active = false;
                showFallback(response.status === 404 ? 'Zamówienie jest niedostępne.' : 'Sesja wygasła. Zaloguj się ponownie.');
                return;
            }
            if (!response.ok) throw new Error('Status unavailable');

            const next = await response.json();
            if (typeof next.label !== 'string' || typeof next.watch !== 'boolean') throw new Error('Invalid status');
            if (status.textContent !== next.label) status.textContent = next.label;
            if (detail && detail.textContent !== next.detail) detail.textContent = next.detail;
            if (amount) amount.textContent = next.amount_label;
            if (payment && !next.show_payment) payment.remove();
            showFallback('');
            active = next.watch;
        } catch {
            delay = 15000;
            showFallback('Nie udało się sprawdzić statusu. Spróbujemy ponownie.');
        } finally {
            running = false;
            schedule(delay);
        }
    }

    document.addEventListener('visibilitychange', () => schedule(0));
    schedule(0);
})();
