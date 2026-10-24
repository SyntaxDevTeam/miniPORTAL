(() => {
  'use strict';
  const localizeTimes = (root = document) => {
    const format = new Intl.DateTimeFormat('pl-PL', {
      year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', timeZoneName: 'short'
    });
    root.querySelectorAll('time[data-local-time]').forEach(element => {
      const value = new Date(element.dateTime);
      if (!Number.isNaN(value.getTime())) {
        element.textContent = format.format(value);
        element.title = Intl.DateTimeFormat().resolvedOptions().timeZone;
      }
    });
  };
  const heart = (id, label, on) => {
    const button = document.createElement('button');
    button.type = 'button'; button.className = 'profile-stat-heart'; button.dataset.statisticHeart = id;
    setHeart(button, label, on);
    return button;
  };
  const setHeart = (button, label, on) => {
    button.setAttribute('aria-pressed', String(on));
    button.setAttribute('aria-label', `${on ? 'Usuń z wyróżnionych' : 'Dodaj do wyróżnionych'}: ${label}`);
    button.title = button.getAttribute('aria-label');
    const icon = document.createElement('span'); icon.setAttribute('aria-hidden', 'true'); icon.textContent = on ? '♥' : '♡';
    button.replaceChildren(icon);
  };
  const paint = (section, result) => {
    const selected = new Set(result.selected);
    const grid = section.querySelector('[data-statistic-highlights]');
    const cards = result.statistics.map((stat, index) => {
      const card = document.createElement('div'); card.className = 'profile-stat';
      const number = document.createElement('span'); number.className = 'profile-stat-number'; number.setAttribute('aria-hidden', 'true'); number.textContent = String(index + 1).padStart(2, '0');
      const value = document.createElement('strong'); value.textContent = stat.value;
      const label = document.createElement('span'); label.className = 'profile-stat-label'; label.textContent = stat.label;
      card.append(number, heart(stat.id, stat.label, true), value, label);
      if (stat.note) { const note = document.createElement('small'); note.textContent = stat.note; card.append(note); }
      return card;
    });
    grid.replaceChildren(...cards);
    section.querySelectorAll('.profile-stat-group [data-statistic-heart]').forEach(button => {
      const on = selected.has(button.dataset.statisticHeart);
      if (button.getAttribute('aria-pressed') !== String(on)) setHeart(button, button.closest('div').querySelector('dt').textContent, on);
    });
  };
  localizeTimes();
  document.querySelectorAll('[data-statistic-favorites]').forEach(section => {
    let pending = false;
    section.addEventListener('click', async event => {
      const button = event.target.closest('[data-statistic-heart]');
      if (!button || !section.contains(button) || pending) return;
      const status = section.querySelector('.profile-stat-save');
      const wasOn = button.getAttribute('aria-pressed') === 'true';
      pending = true; section.setAttribute('aria-busy', 'true');
      section.querySelectorAll('[data-statistic-heart]').forEach(item => { item.disabled = true; });
      status.textContent = 'Zapisywanie…';
      const controller = new AbortController();
      const timeout = setTimeout(() => controller.abort(), 15000);
      try {
        const response = await fetch(section.dataset.statisticFavorites, {
          method: 'POST', credentials: 'same-origin', signal: controller.signal, headers: { Accept: 'application/json' },
          body: new URLSearchParams({ _token: section.dataset.csrf, server: section.dataset.server, statistic: button.dataset.statisticHeart, selected: wasOn ? '0' : '1' })
        });
        const result = await response.json();
        if (!response.ok || result.code !== 'SAVED' || !Array.isArray(result.selected) || !Array.isArray(result.statistics)) throw new Error('Save failed');
        paint(section, result);
        status.textContent = wasOn ? 'Usunięto z wyróżnionych.' : 'Dodano do wyróżnionych.';
        const replacement = button.isConnected ? button : [...section.querySelectorAll('[data-statistic-heart]')].find(item => item.dataset.statisticHeart === button.dataset.statisticHeart && item.getClientRects().length > 0);
        if (replacement) { replacement.disabled = false; replacement.focus({ preventScroll: true }); }
      } catch (_) {
        status.textContent = 'Nie udało się zapisać zmiany. Spróbuj ponownie; jeśli sesja wygasła, zaloguj się.';
      } finally {
        clearTimeout(timeout);
        pending = false; section.removeAttribute('aria-busy');
        section.querySelectorAll('[data-statistic-heart]').forEach(item => { item.disabled = false; });
      }
    });
  });
})();
