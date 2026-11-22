(() => {
    'use strict';
    document.querySelectorAll('[data-task-prompt]').forEach((panel) => {
        if (panel.dataset.promptReady === 'true') return;
        panel.dataset.promptReady = 'true';
        const text = panel.querySelector('[data-task-prompt-text]');
        const button = panel.querySelector('[data-task-prompt-copy]');
        const status = panel.querySelector('[data-task-prompt-status]');
        if (!text || !button || !status) return;
        button.addEventListener('click', async () => {
            button.disabled = true;
            try {
                if (!navigator.clipboard?.writeText) throw new Error('clipboard unavailable');
                await navigator.clipboard.writeText(text.value);
                status.textContent = 'Prompt skopiowany. Wklej go do wybranego czatu AI/SI.';
            } catch {
                text.focus();
                text.select();
                status.textContent = 'Tekst zaznaczony. Skopiuj go skrótem Ctrl+C lub opcją „Kopiuj” na telefonie.';
            } finally {
                button.disabled = false;
            }
        });
    });
})();
