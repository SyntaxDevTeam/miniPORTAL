(() => {
    'use strict';

    document.querySelectorAll('.admin-plugin-files-modal').forEach((modal) => {
        if (modal.parentElement !== document.body) document.body.appendChild(modal);
        if (modal.dataset.autoOpen === 'true' && window.bootstrap?.Modal) {
            window.bootstrap.Modal.getOrCreateInstance(modal).show();
        }
    });

    document.querySelectorAll('[data-admin-catalog-card]').forEach((card) => {
        const toggle = card.querySelector('.admin-catalog-choice');
        if (!(toggle instanceof HTMLInputElement)) return;
        const select = document.getElementById(toggle.getAttribute('aria-controls') || '');
        const sync = () => {
            card.classList.toggle('is-selected', toggle.checked);
            card.setAttribute('aria-label', `${toggle.checked ? 'Wybrano' : 'Nie wybrano'}: ${toggle.getAttribute('aria-label')?.replace(/^Wybierz\s+/, '') || 'plugin'}`);
            if (select instanceof HTMLSelectElement) select.disabled = !toggle.checked;
        };
        const change = (checked) => {
            toggle.checked = checked;
            toggle.dispatchEvent(new Event('change', { bubbles: true }));
        };
        card.addEventListener('click', (event) => {
            if (event.target.closest('select, option, input, label, button, a')) return;
            change(!toggle.checked);
        });
        card.addEventListener('keydown', (event) => {
            if (event.target !== card || !['Enter', ' '].includes(event.key)) return;
            event.preventDefault();
            change(!toggle.checked);
        });
        if (select instanceof HTMLSelectElement) {
            select.addEventListener('change', () => { if (!toggle.checked) change(true); });
        }
        toggle.addEventListener('change', sync);
        sync();
    });

    document.querySelectorAll('.admin-plugin-files-modal form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.dataset.confirm || 'Czy na pewno?')) event.preventDefault();
        });
    });
})();
