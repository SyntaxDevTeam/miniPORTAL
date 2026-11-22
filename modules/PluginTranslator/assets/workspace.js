(function () {
  'use strict';
  const protectedTokens = (text) => (text.match(/%[A-Za-z_][A-Za-z0-9_]*%|%(?:[0-9]+\$)?[-+0-9.]*[sdif]|\{\{[^{}\r\n]+\}\}|\{[A-Za-z0-9_.-]+\}|<[^<>\r\n]+>|(?:&|§)#[0-9a-fA-F]{6}|(?:&|§)[0-9a-fA-Fk-oK-OrRxX]/gu) || []).sort();
  document.querySelectorAll('[data-text-workspace]').forEach((workspace) => {
    const rows = Array.from(workspace.querySelectorAll('[data-workspace-row]'));
    const search = workspace.querySelector('[data-workspace-search]');
    const filter = workspace.querySelector('[data-workspace-filter]');
    let focusedRow = -1;
    workspace.addEventListener('focusin', (event) => {
      const index = rows.findIndex((row) => row.contains(event.target));
      if (index >= 0) focusedRow = index;
    });
    const refresh = () => {
      let completed = 0, issues = 0, visible = 0;
      rows.forEach((row) => {
        const field = row.querySelector('textarea');
        const filled = field.value.trim() !== '';
        const invalid = filled && JSON.stringify(protectedTokens(field.value)) !== JSON.stringify(JSON.parse(row.dataset.protected));
        completed += Number(filled); issues += Number(invalid);
        row.dataset.missing = filled ? '0' : '1';
        field.setAttribute('aria-invalid', String(invalid));
        row.querySelector('[data-message-quality]').textContent = invalid ? 'Keep the original placeholders, tags and colour codes.' : filled ? 'Completed' : 'Untranslated — original will be kept';
        const text = [row.querySelector('[data-message-key]').textContent, row.querySelector('[data-message-source]').textContent, field.value].join(' ').toLowerCase();
        row.hidden = !text.includes(search.value.toLowerCase()) || (filter.value === 'missing' && filled) || (filter.value === 'complete' && !filled) || (filter.value === 'issues' && !invalid);
        visible += Number(!row.hidden);
      });
      workspace.querySelector('[data-workspace-progress]').textContent = `${completed} / ${rows.length} completed · ${issues} quality issues · ${visible} shown`;
      workspace.querySelector('[data-workspace-meter]').value = completed;
      workspace.querySelector('[data-workspace-empty]').hidden = visible !== 0;
    };
    workspace.addEventListener('input', refresh);
    filter.addEventListener('change', refresh);
    workspace.addEventListener('workspace:restored', refresh);
    workspace.addEventListener('click', (event) => {
      const button = event.target.closest('button');
      if (!button) return;
      const row = button.closest('[data-workspace-row]');
      if (row) {
        const field = row.querySelector('textarea');
        if (button.hasAttribute('data-copy-source')) field.value = row.querySelector('[data-message-source]').textContent;
        else if (button.hasAttribute('data-insert-token')) field.setRangeText(button.dataset.insertToken, field.selectionStart, field.selectionEnd, 'end');
        else return;
        field.focus();
        field.dispatchEvent(new Event('input', { bubbles: true }));
      } else if (button.hasAttribute('data-next-missing')) {
        const active = focusedRow;
        const next = [...rows.slice(active + 1), ...rows.slice(0, active + 1)].find((item) => item.dataset.missing === '1' && !item.hidden);
        if (next) next.querySelector('textarea').focus();
      }
    });
    refresh();
  });
})();
