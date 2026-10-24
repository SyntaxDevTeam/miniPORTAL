(() => {
  'use strict';
  document.querySelectorAll('[data-card-studio]').forEach(studio => {
    studio.classList.add('is-enhanced');
    const form = studio.querySelector('[data-card-form]');
    const panel = studio.querySelector('.profile-panel');
    const options = JSON.parse(studio.dataset.cardStatistics);
    const choices = studio.querySelector('[data-card-stat-choices]');
    const identity = panel.querySelector('.profile-identity');
    const metrics = panel.querySelector('.profile-metrics');
    const statusAllowed = ['Online', 'Offline'].includes(studio.dataset.cardStatus);
    let status = panel.querySelector('.profile-status');
    if (!status && statusAllowed) {
      status = document.createElement('p'); status.className = 'profile-status' + (studio.dataset.cardStatus === 'Online' ? ' is-online' : '');
      status.append(document.createElement('span'), document.createTextNode(studio.dataset.cardStatus));
      identity.insertBefore(status, metrics);
    }
    let bio = panel.querySelector('.profile-bio');
    if (!bio) { bio = document.createElement('p'); bio.className = 'profile-bio'; identity.insertBefore(bio, metrics); }
    let time = metrics.children[1];
    // The server supplies the public play time as a neutral, inert template value.
    if (!time) {
      const template = studio.querySelector('[data-card-time]');
      if (template) { time = template.content.firstElementChild.cloneNode(true); metrics.append(time); }
    }
    const input = name => form.elements.namedItem(name);
    const make = (tag, className, text) => { const e = document.createElement(tag); e.className = className; if (text !== undefined) e.textContent = text; return e; };
    function update(dirty = true) {
      ['palette', 'layout', 'backdrop'].forEach(name => {
        [...panel.classList].filter(c => c.startsWith('profile-' + name + '-')).forEach(c => panel.classList.remove(c));
        panel.classList.add('profile-' + name + '-' + input(name).value);
      });
      panel.classList.remove('profile-stats-tiles', 'profile-stats-strip');
      panel.classList.add('profile-stats-' + input('stat_style').value);
      bio.textContent = input('bio').value.trim(); bio.hidden = !bio.textContent;
      studio.querySelector('[data-card-bio-count]').textContent = [...input('bio').value].length + '/280';
      if (status) status.hidden = !input('show_status').checked;
      if (time) time.hidden = !input('show_time').checked;
      const selected = [...choices.querySelectorAll('input:checked')].map(e => e.value);
      let section = panel.querySelector('.profile-statistics');
      if (!section && selected.length) {
        section = make('section', 'profile-statistics'); section.setAttribute('aria-label', 'Statystyki gracza');
        const heading = make('div', 'profile-section-label'); heading.append(make('span', '', 'Twoja przygoda w liczbach'));
        section.append(heading, make('div', 'profile-stat-grid'));
        panel.insertBefore(section, panel.querySelector('.profile-footer'));
      }
      if (section) {
        section.hidden = selected.length === 0;
        const grid = section.querySelector('.profile-stat-grid'); grid.replaceChildren();
        selected.forEach((id, index) => {
          const data = options[id]; if (!data || data.value === '—') return;
          const stat = make('div', 'profile-stat'); const number = make('span', 'profile-stat-number', String(index + 1).padStart(2, '0')); number.setAttribute('aria-hidden', 'true');
          stat.append(number, make('strong', '', data.value), make('span', 'profile-stat-label', data.label)); grid.append(stat);
        });
        section.hidden = grid.children.length === 0;
      }
      [...choices.children].forEach((row, i, rows) => {
        row.querySelector('input').name = 'stat_' + i;
        const checked = row.querySelector('input').checked;
        const index = selected.indexOf(row.dataset.cardStat);
        row.querySelector('[data-card-move="up"]').disabled = !checked || index <= 0;
        row.querySelector('[data-card-move="down"]').disabled = !checked || index === selected.length - 1;
      });
      if (dirty) studio.querySelector('[data-card-save-state]').textContent = 'Masz niezapisane zmiany. Podgląd jest aktualny.';
    }
    form.addEventListener('input', () => update());
    choices.addEventListener('click', event => {
      const button = event.target.closest('[data-card-move]'); if (!button) return;
      const row = button.closest('[data-card-stat]');
      const selected = [...choices.children].filter(e => e.querySelector('input').checked);
      const index = selected.indexOf(row); const up = button.dataset.cardMove === 'up';
      const other = selected[index + (up ? -1 : 1)]; if (!other) return;
      choices.insertBefore(row, up ? other : other.nextSibling); update();
      // Keep keyboard focus on the moved tile even when its arrow becomes disabled.
      row.querySelector('input').focus();
    });
    form.addEventListener('submit', () => update(false));
    update(false);
  });
})();
