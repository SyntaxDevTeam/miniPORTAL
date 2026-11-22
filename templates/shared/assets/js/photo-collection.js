(() => {
  'use strict';
  document.querySelectorAll('.mv-gallery, [data-gallery-root]').forEach(root => {
    if (root.dataset.ready) return;
    root.dataset.ready = '1';
    const dialog = root.querySelector('dialog');
    const photos = [...root.querySelectorAll('[data-gallery-photo]')];
    let active = 0, opener;
    function show(index) {
      active = (index + photos.length) % photos.length;
      const item = photos[active], img = dialog.querySelector('img');
      img.src = item.href; img.alt = item.dataset.title;
      dialog.querySelector('h2').textContent = item.dataset.title;
      dialog.querySelector('.mv-gallery-dialog-caption p').textContent = item.dataset.description;
      dialog.querySelector('[data-gallery-position]').textContent = `${active + 1} / ${photos.length}`;
    }
    photos.forEach((item, i) => item.addEventListener('click', event => {
      if (event.ctrlKey || event.metaKey || !dialog.showModal) return;
      event.preventDefault(); opener = item; show(i); dialog.showModal();
    }));
    dialog.querySelector('[data-gallery-close]').addEventListener('click', () => dialog.close());
    dialog.querySelector('[data-gallery-prev]').addEventListener('click', () => show(active - 1));
    dialog.querySelector('[data-gallery-next]').addEventListener('click', () => show(active + 1));
    dialog.addEventListener('keydown', event => {
      if (event.key === 'ArrowLeft') { event.preventDefault(); show(active - 1); }
      if (event.key === 'ArrowRight') { event.preventDefault(); show(active + 1); }
    });
    dialog.addEventListener('close', () => opener?.focus());
    const approval = root.querySelector('[data-gallery-approval]');
    if (approval) {
      const choices = [...root.querySelectorAll('[data-gallery-select]')];
      const all = approval.querySelector('[data-gallery-select-all]');
      const button = approval.querySelector('[data-gallery-approve]');
      const count = approval.querySelector('[data-gallery-selection-count]');
      const status = approval.querySelector('[data-gallery-approval-status]');
      const progress = approval.querySelector('progress');
      const refresh = approval.querySelector('[data-gallery-approval-refresh]');
      let busy = false;
      function updateSelection() {
        const available = choices.filter(input => !input.disabled);
        const selected = available.filter(input => input.checked);
        all.checked = available.length > 0 && selected.length === available.length;
        all.indeterminate = selected.length > 0 && selected.length < available.length;
        all.disabled = busy || available.length === 0;
        button.disabled = busy || selected.length === 0;
        count.textContent = `Zaznaczono: ${selected.length} z ${available.length}`;
        choices.forEach(input => input.closest('.mv-gallery-card, [data-gallery-item]').classList.toggle('is-selected', input.checked && !input.disabled));
      }
      all.addEventListener('change', () => { choices.filter(input => !input.disabled).forEach(input => { input.checked = all.checked; }); updateSelection(); });
      choices.forEach(input => input.addEventListener('change', updateSelection));
      approval.addEventListener('submit', async event => {
        event.preventDefault();
        if (busy) return;
        const selected = choices.filter(input => input.checked && !input.disabled);
        if (!selected.length) return;
        busy = true; all.disabled = true; button.disabled = true;
        choices.forEach(input => { input.disabled = true; });
        progress.hidden = false; progress.value = 0; progress.max = selected.length;
        refresh.hidden = true;
        const succeeded = new Set(); let failed = 0;
        for (const [index, input] of selected.entries()) {
          status.textContent = `Zatwierdzanie ${index + 1} z ${selected.length}…`;
          const card = input.closest('.mv-gallery-card, [data-gallery-item]');
          let result = card.querySelector('[data-gallery-approval-result]');
          if (!result) { result = document.createElement('p'); result.dataset.galleryApprovalResult = ''; result.className = 'mv-gallery-feedback'; card.querySelector('.mv-gallery-caption, [data-gallery-caption]').append(result); }
          const body = new FormData();
          body.set('_token', approval.elements._token.value); body.append('ids[]', input.value);
          body.set(`revisions[${input.value}]`, input.dataset.revision);
          try {
            const response = await fetch(approval.action, { method: 'POST', credentials: 'same-origin', headers: { 'X-Requested-With': 'GalleryModeration' }, body });
            const data = await response.json().catch(() => { throw new Error('Brak potwierdzenia publikacji. Możesz bezpiecznie ponowić zatwierdzenie.'); });
            if (!response.ok || String(data.id) !== input.value || data.status !== 'published') throw new Error(data.error || 'Nie udało się zatwierdzić zdjęcia.');
            succeeded.add(input); input.checked = false; input.closest('label').hidden = true;
            const badge = card.querySelector('.mv-gallery-status');
            badge.dataset.state = 'published'; badge.textContent = 'Opublikowano';
            card.classList.remove('is-selected'); result.textContent = 'Zdjęcie opublikowane.';
          } catch (error) {
            failed++; result.textContent = error instanceof TypeError ? 'Błąd połączenia. Ponów zatwierdzenie zdjęcia.' : error.message;
          }
          progress.value = index + 1;
        }
        choices.forEach(input => { input.disabled = input.closest('label').hidden || succeeded.has(input); });
        busy = false; updateSelection(); refresh.hidden = false;
        status.textContent = `Opublikowano: ${succeeded.size} z ${selected.length}.` + (failed ? ` Błędy: ${failed}. Pozostałe zdjęcia możesz ponowić lub sprawdzić pojedynczo.` : ' Możesz przejść do kolejnej partii.');
      });
      window.addEventListener('beforeunload', event => { if (busy) { event.preventDefault(); event.returnValue = ''; } });
      updateSelection();
    }

    const bulk = root.querySelector('[data-gallery-bulk]');
    if (bulk) {
      const input = bulk.querySelector('[type=file]');
      const drop = bulk.querySelector('[data-gallery-bulk-drop]');
      const list = bulk.querySelector('[data-gallery-bulk-list]');
      const status = bulk.querySelector('[data-gallery-bulk-status]');
      const start = bulk.querySelector('[data-gallery-bulk-start]');
      const clear = bulk.querySelector('[data-gallery-bulk-clear]');
      const describe = bulk.querySelector('[data-gallery-bulk-describe]');
      const progress = bulk.querySelector('progress');
      const category = bulk.querySelector('[name=category_id]');
      let queue = [], busy = false;
      function summary() {
        const ids = queue.filter(item => item.id).map(item => item.id);
        describe.hidden = ids.length === 0;
        if (ids.length) describe.href = '/index.php?route=/admin/gallery&batch=' + ids.join(',') + '#gallery-describe';
        clear.hidden = queue.length === 0;
        progress.hidden = queue.length === 0;
        progress.max = Math.max(1, queue.length);
        progress.value = queue.filter(item => item.id || item.failed).length;
      }
      function enqueue(files) {
        if (busy) return;
        const count = files.length;
        if (queue.length + count > 200) { status.textContent = 'W kolejce mieści się do 200 zdjęć. Wgraj obecną partię, a następnie dodaj kolejną.'; return; }
        for (const file of files) {
          const row = document.createElement('li');
          const name = document.createElement('strong'); name.textContent = file.name;
          const result = document.createElement('span'); result.textContent = 'Gotowe do przesłania';
          const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'mv-gallery-secondary'; remove.textContent = 'Usuń z kolejki';
          const item = { file, row, result, key: crypto.randomUUID(), failed: false, id: null };
          if (file.size > 8388608 || !['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
            item.invalid = true; item.failed = true; result.textContent = 'Wymagany JPG, PNG lub WebP do 8 MiB.';
          }
          row.append(name, result, remove); list.append(row); queue.push(item);
          remove.addEventListener('click', () => { if (busy || item.id) return; queue = queue.filter(x => x !== item); row.remove(); summary(); });
        }
        input.value = ''; summary();
        status.textContent = `Zdjęć w kolejce: ${queue.length}. Wybierz kategorię i rozpocznij przesyłanie.`;
      }
      input.required = false;
      input.addEventListener('change', () => enqueue([...input.files]));
      ['dragenter', 'dragover'].forEach(type => drop.addEventListener(type, event => { event.preventDefault(); if (!busy) drop.classList.add('is-dragging'); }));
      drop.addEventListener('dragleave', () => drop.classList.remove('is-dragging'));
      drop.addEventListener('drop', event => { event.preventDefault(); drop.classList.remove('is-dragging'); enqueue([...event.dataTransfer.files]); });
      clear.addEventListener('click', () => {
        if (busy) return;
        if (queue.some(item => item.id) && !window.confirm('Wyczyścić kolejkę? Zdjęcia są już zapisane. Najpierw użyj „Opisz wgrane zdjęcia”, jeśli chcesz opisać całą partię.')) return;
        queue = []; list.replaceChildren(); status.textContent = ''; summary();
      });
      bulk.addEventListener('submit', async event => {
        event.preventDefault();
        if (busy || !category.reportValidity()) return;
        const pending = queue.filter(item => !item.id && !item.invalid);
        if (!pending.length) { status.textContent = 'Dodaj poprawne zdjęcia do kolejki.'; return; }
        busy = true; start.disabled = true; clear.disabled = true; input.disabled = true; category.disabled = true;
        describe.setAttribute('aria-disabled', 'true');
        const selectedCategory = category.value;
        for (const item of pending) {
          item.failed = false; item.result.textContent = 'Przesyłanie…';
          status.textContent = `Przesyłanie ${pending.indexOf(item) + 1} z ${pending.length}: ${item.file.name}`;
          const body = new FormData(); body.set('_token', bulk.elements._token.value); body.set('category_id', selectedCategory);
          body.set('photo', item.file); body.set('upload_key', item.key);
          try {
            const response = await fetch(bulk.action, { method: 'POST', credentials: 'same-origin', headers: { 'X-Requested-With': 'GalleryQueue' }, body });
            const data = await response.json().catch(() => { throw new Error('Brak potwierdzenia zapisu. Ponów przesyłanie — zapisane zdjęcie nie zostanie zduplikowane.'); });
            if (!response.ok || !Number.isSafeInteger(data.id) || data.id < 1) throw new Error(data.error || 'Nie udało się zapisać zdjęcia.');
            item.id = data.id; item.result.textContent = 'Opublikowano'; item.row.dataset.state = 'success';
            item.row.querySelector('button').hidden = true;
          } catch (error) {
            item.failed = true; item.result.textContent = error instanceof TypeError ? 'Błąd połączenia. Ponów przesyłanie zdjęcia.' : (error.message || 'Nie udało się przesłać zdjęcia. Spróbuj ponownie.'); item.row.dataset.state = 'error';
          }
          summary();
        }
        busy = false; start.disabled = false; clear.disabled = false; input.disabled = false; category.disabled = false;
        describe.removeAttribute('aria-disabled');
        const failed = queue.filter(item => item.failed).length;
        status.textContent = `Opublikowano ${queue.filter(item => item.id).length} z ${queue.length} zdjęć.` + (failed ? ` Błędy: ${failed}. Popraw pliki lub ponów przesyłanie.` : ' Możesz teraz opisać całą partię.');
        start.textContent = failed ? 'Ponów nieudane zdjęcia' : 'Wgraj kolejne zdjęcia'; summary();
      });
      describe.addEventListener('click', event => { if (busy) event.preventDefault(); });
      window.addEventListener('beforeunload', event => { if (busy) { event.preventDefault(); event.returnValue = ''; } });
    }

    const form = root.querySelector('[data-gallery-upload]');
    if (!form) return;
    const input = form.querySelector('[type=file]'), preview = form.querySelector('[data-gallery-preview]');
    const drop = input.closest('label'), status = form.querySelector('[data-gallery-progress]');
    let generation = 0;
    function update() {
      const current = ++generation, file = input.files[0];
      preview.hidden = true; preview.removeAttribute('src'); input.setCustomValidity('');
      if (!file) return;
      if (file.size > 8388608 || !['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
        input.setCustomValidity('Wybierz JPG, PNG lub WebP do 8 MiB.'); input.reportValidity(); return;
      }
      const reader = new FileReader();
      reader.onload = () => { if (current === generation) { preview.src = reader.result; preview.hidden = false; } };
      reader.readAsDataURL(file);
      const title = form.elements.title;
      if (!title.value) title.value = file.name.replace(/\.[^.]+$/, '').slice(0, 120);
    }
    input.addEventListener('change', update);
    ['dragenter', 'dragover'].forEach(type => drop.addEventListener(type, event => {
      event.preventDefault(); drop.classList.add('is-dragging');
    }));
    drop.addEventListener('dragleave', () => drop.classList.remove('is-dragging'));
    drop.addEventListener('drop', event => {
      event.preventDefault(); drop.classList.remove('is-dragging');
      if (event.dataTransfer.files.length !== 1) { status.textContent = 'Wybierz jedno zdjęcie na zgłoszenie.'; return; }
      input.files = event.dataTransfer.files; update();
    });
    form.addEventListener('submit', () => {
      form.querySelector('[type=submit]').disabled = true;
      status.textContent = 'Przesyłanie zdjęcia…';
    });
    if (location.hash === '#gallery-upload') root.querySelector('#gallery-upload').open = true;
  });
})();
