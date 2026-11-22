(() => {
    const preview = document.querySelector('[data-shop-message-preview]');
    const prefix = document.querySelector('input[name="message_prefix"]');
    const form = prefix?.form;
    if (!preview || !form) return;
    const fields = ['message_prefix', 'message_format', 'player_message', 'broadcast_message', 'name', '_token'];
    const feedback = preview.querySelector('[data-shop-message-feedback]');
    const formatLabel = preview.querySelector('[data-shop-message-format]');
    const formatWarning = preview.querySelector('[data-shop-message-format-warning]');
    const miniTags = /(?<!\\)(?:\\\\)*<\/?(?:black|dark_blue|dark_green|dark_aqua|dark_red|dark_purple|gold|gray|dark_gray|blue|green|aqua|red|light_purple|yellow|white|colou?r|#[0-9a-f]{6}|bold|b|italic|i|em|underlined|u|strikethrough|st|gradient|rainbow|transition|reset)(?=[:>])/i;
    let timer;
    let sequence = 0;
    let controller;

    function render(target, runs, empty) {
        const fragment = document.createDocumentFragment();
        if (runs.length === 0) fragment.append(empty);
        for (const [text, color, mask] of runs) {
            const span = document.createElement('span');
            span.textContent = text;
            if (/^#[0-9a-f]{6}$/i.test(color)) span.style.color = color;
            if (mask & 1) span.style.fontWeight = 'bold';
            if (mask & 2) span.style.fontStyle = 'italic';
            const lines = [];
            if (mask & 4) lines.push('underline');
            if (mask & 8) lines.push('line-through');
            if (lines.length) span.style.textDecoration = lines.join(' ');
            fragment.append(span);
        }
        target.replaceChildren(fragment);
    }

    async function refresh(current) {
        controller = new AbortController();
        const body = new URLSearchParams();
        for (const field of fields) body.set(field, form.elements.namedItem(field)?.value ?? '');
        try {
            const reply = await fetch('/admin/moonvale-shop/message-preview', {
                method: 'POST', body, cache: 'no-store', signal: controller.signal,
                headers: { Accept: 'application/json' },
            });
            const data = await reply.json();
            if (current !== sequence) return;
            if (!reply.ok) throw new Error(data.error || 'Nie udało się odświeżyć podglądu.');
            render(preview.querySelector('[data-shop-message-output="player"]'), data.player, 'Brak wiadomości');
            render(preview.querySelector('[data-shop-message-output="broadcast"]'), data.broadcast, 'Brak ogłoszenia');
            feedback.textContent = '';
        } catch (error) {
            if (current === sequence && error.name !== 'AbortError') {
                for (const output of preview.querySelectorAll('[data-shop-message-output]')) output.textContent = 'Podgląd niedostępny';
                feedback.textContent = error.message;
            }
        }
    }

    function schedule() {
        const mini = form.elements.namedItem('message_format').value === 'minimessage';
        formatLabel.textContent = mini ? 'Aktywny format: MiniMessage — np. <green>tekst</green>, <b>pogrubienie</b>.'
            : 'Aktywny format: klasyczny — kody &a, &l. Znaczniki MiniMessage nie są interpretowane.';
        formatWarning.hidden = mini || !['message_prefix', 'player_message', 'broadcast_message']
            .some(name => miniTags.test(form.elements.namedItem(name)?.value ?? ''));
        feedback.textContent = 'Aktualizowanie podglądu…';
        const current = ++sequence;
        controller?.abort();
        clearTimeout(timer);
        timer = setTimeout(() => refresh(current), 500);
    }

    function changed(event) {
        if (fields.includes(event.target.name)) schedule();
    }
    form.addEventListener('input', changed);
    form.addEventListener('change', changed);
    preview.querySelector('[data-shop-message-use-mini]').addEventListener('click', () => {
        const format = form.elements.namedItem('message_format');
        format.value = 'minimessage';
        format.dispatchEvent(new Event('change', { bubbles: true }));
    });
    schedule();
})();
