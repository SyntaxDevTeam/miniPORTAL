(() => {
    'use strict';
    const root = document.querySelector('[data-asset-manager]');
    if (!root || root.dataset.initialized) return;
    root.dataset.initialized = 'true';
    const find = (selector) => root.querySelector(selector);
    const all = (selector) => Array.from(root.querySelectorAll(selector));
    const copyStatus = find('[data-copy-status]');
    root.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-copy-url]');
        if (!button) return;
        const url = new URL(button.dataset.copyUrl, location.href).href;
        try {
            await navigator.clipboard.writeText(url);
            copyStatus.textContent = 'Skopiowano adres grafiki.';
        } catch {
            const field = document.createElement('input');
            field.value = url; field.readOnly = true;
            copyStatus.replaceChildren(document.createTextNode('Skopiuj adres z pola: '), field);
            field.focus(); field.select();
        }
    });
    const selections = all('[data-select-asset]');
    const selectAll = find('[data-select-all]');
    const operation = find('[data-operation]');
    const updateSelection = () => {
        const count = selections.filter((input) => input.checked).length;
        selections.forEach((input) => input.closest('[data-asset]').classList.toggle('is-selected', input.checked));
        if (selectAll) {
            selectAll.checked = count === selections.length && count > 0;
            selectAll.indeterminate = count > 0 && count < selections.length;
            find('[data-selection-count]').textContent = `${count} zaznaczonych`;
            find('[data-bulk-submit]').disabled = count === 0;
        }
    };
    selectAll?.addEventListener('change', () => { selections.forEach((input) => { input.checked = selectAll.checked; }); updateSelection(); });
    selections.forEach((input) => input.addEventListener('change', updateSelection));
    updateSelection();
    const updateOperation = () => {
        if (!operation) return;
        find('[data-bulk-category]').hidden = operation.value !== 'category';
        find('[data-bulk-confirm]').hidden = operation.value !== 'delete';
        find('[name=confirm_delete]').required = operation.value === 'delete';
    };
    operation?.addEventListener('change', updateOperation); updateOperation();
    find('[data-bulk-form]')?.addEventListener('submit', (event) => {
        if (operation?.value === 'delete' && !confirm('Trwale usunąć zaznaczone grafiki? Ich adresy przestaną działać również w istniejących treściach.')) event.preventDefault();
    });
    const dialog = find('[data-preview-dialog]');
    const assets = all('[data-asset]');
    let current = 0;
    const showPreview = (index) => {
        current = (index + assets.length) % assets.length;
        const asset = assets[current].dataset;
        find('[data-preview-title]').textContent = asset.title;
        const image = find('[data-preview-image]'); image.src = asset.url; image.alt = asset.alt;
        find('[data-preview-details]').textContent = asset.details;
        find('[data-preview-open]').href = asset.url;
        find('[data-preview-previous]').disabled = assets.length < 2;
        find('[data-preview-next]').disabled = assets.length < 2;
        if (!dialog.open) dialog.showModal();
    };
    root.addEventListener('click', (event) => {
        const link = event.target.closest('[data-preview]');
        if (!link || !dialog?.showModal || event.ctrlKey || event.metaKey || event.shiftKey) return;
        event.preventDefault(); showPreview(Number(link.dataset.preview));
    });
    find('[data-close-preview]')?.addEventListener('click', () => dialog.close());
    find('[data-preview-previous]')?.addEventListener('click', () => showPreview(current - 1));
    find('[data-preview-next]')?.addEventListener('click', () => showPreview(current + 1));
    dialog?.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') { event.preventDefault(); showPreview(current + (event.key === 'ArrowLeft' ? -1 : 1)); }
    });
    dialog?.addEventListener('click', (event) => { if (event.target === dialog) { const r = dialog.getBoundingClientRect(); if (event.clientX < r.left || event.clientX > r.right || event.clientY < r.top || event.clientY > r.bottom) dialog.close(); } });

    const form = find('[data-upload-form]');
    if (!form || !window.XMLHttpRequest || !window.FormData || !window.FileReader) return;
    const input = find('#am-files'), queue = find('[data-upload-queue]'), status = find('[data-upload-status]');
    const start = find('[data-upload-start]'), clear = find('[data-clear-queue]'), done = find('[data-upload-done]');
    const editForm = find('[data-upload-edit]'), uploadedIds = find('[data-uploaded-ids]'), editButton = find('[data-upload-edit-button]');
    const allowed = ['image/png', 'image/jpeg', 'image/webp', 'image/gif', 'image/svg+xml'];
    let entries = [], running = false;
    const bytes = (size) => `${(size / 1048576).toFixed(2)} MiB`;
    const renderQueue = () => {
        queue.replaceChildren();
        entries.forEach((entry) => {
            const row = document.createElement('li'); row.className = 'am-queue-item'; row.dataset.state = entry.state;
            const previewBox = document.createElement('span'); previewBox.className = 'am-queue-preview';
            const placeholder = document.createElement('span'); placeholder.className = 'am-queue-placeholder';
            placeholder.setAttribute('role', 'img');
            const format = entry.file.name.split('.').pop().toUpperCase().slice(0, 5);
            placeholder.textContent = entry.preview ? format : '…';
            placeholder.setAttribute('aria-label', 'Ładowanie podglądu');
            const preview = document.createElement('img'); preview.alt = ''; preview.hidden = true;
            preview.addEventListener('load', () => { preview.hidden = false; placeholder.hidden = true; });
            preview.addEventListener('error', () => { preview.hidden = true; placeholder.hidden = false; placeholder.textContent = format; placeholder.setAttribute('aria-label', 'Podgląd niedostępny'); });
            if (entry.preview) preview.src = entry.preview;
            else if (entry.previewError) { placeholder.textContent = format; placeholder.setAttribute('aria-label', 'Podgląd niedostępny'); }
            previewBox.append(placeholder, preview);
            const text = document.createElement('div'), title = document.createElement('strong'), info = document.createElement('span'), progress = document.createElement('progress');
            title.textContent = entry.file.name; info.textContent = entry.message || bytes(entry.file.size); progress.max = 100; progress.value = entry.percent; progress.setAttribute('aria-label', `Postęp: ${entry.file.name}`);
            text.append(title, info, progress);
            const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'am-button'; remove.textContent = '×'; remove.setAttribute('aria-label', `Usuń z kolejki: ${entry.file.name}`); remove.disabled = running;
            remove.addEventListener('click', () => { entries = entries.filter((item) => item !== entry); renderQueue(); });
            row.append(previewBox, text, remove); queue.append(row);
        });
        clear.hidden = entries.length === 0; clear.disabled = running;
        start.disabled = running || entries.length === 0 || entries.every((entry) => entry.state === 'done');
        start.textContent = running ? 'Wgrywanie…' : entries.some((entry) => entry.state === 'error') ? 'Ponów nieudane pliki' : 'Wgraj grafiki';
        done.hidden = !entries.some((entry) => entry.state === 'done');
        const uploaded = entries.filter((entry) => entry.state === 'done');
        editForm.hidden = uploaded.length === 0;
        editButton.disabled = running;
        editButton.textContent = `Opisz wgrane grafiki (${uploaded.length})`;
        uploadedIds.replaceChildren(...uploaded.map((entry) => {
            const field = document.createElement('input'); field.type = 'hidden'; field.name = 'ids[]'; field.value = String(entry.id); return field;
        }));
        input.disabled = running; input.required = entries.length === 0;
    };
    const add = (files) => {
        if (running) return;
        const errors = [];
        for (const file of files) {
            const svgFile = /\.svg$/i.test(file.name) && ['', 'image/svg+xml', 'application/xml', 'text/xml', 'text/plain', 'application/octet-stream'].includes(file.type);
            if (!allowed.includes(file.type) && !svgFile && !(/\.(png|jpe?g|webp|gif|svg)$/i.test(file.name) && file.type === '')) { errors.push(`${file.name}: niedozwolony format.`); continue; }
            if (file.size <= 0 || file.size > 5242880) { errors.push(`${file.name}: limit 5 MiB na plik.`); continue; }
            if (entries.some((entry) => entry.file.name === file.name && entry.file.size === file.size && entry.file.lastModified === file.lastModified)) continue;
            if (entries.length >= 20 || entries.reduce((sum, item) => sum + item.file.size, 0) + file.size > 47185920) { errors.push('Zestaw może zawierać do 20 plików i zajmować do 45 MiB.'); break; }
            const key = Array.from(crypto.getRandomValues(new Uint8Array(16)), (byte) => byte.toString(16).padStart(2, '0')).join('');
            const entry = { file, key, preview: '', state: 'pending', percent: 0, message: '' };
            entries.push(entry);
            const reader = new FileReader();
            reader.addEventListener('load', () => {
                if (!entries.includes(entry) || entry.serverPreview) return;
                entry.preview = typeof reader.result === 'string' ? reader.result : '';
                renderQueue();
            });
            reader.addEventListener('error', () => {
                if (!entries.includes(entry) || entry.serverPreview) return;
                entry.previewError = true; renderQueue();
            });
            const extension = file.name.split('.').pop().toLowerCase();
            const type = svgFile ? 'image/svg+xml' : file.type || ({ png: 'image/png', jpg: 'image/jpeg', jpeg: 'image/jpeg', webp: 'image/webp', gif: 'image/gif' })[extension];
            // data: images are permitted by the page CSP; blob: images are not.
            reader.readAsDataURL(new Blob([file], { type }));
        }
        input.value = ''; status.textContent = errors.join(' '); renderQueue();
    };
    input.addEventListener('change', () => add(Array.from(input.files)));
    const dropzone = find('[data-dropzone]');
    ['dragenter', 'dragover'].forEach((name) => dropzone.addEventListener(name, (event) => { event.preventDefault(); dropzone.classList.add('is-dragging'); }));
    dropzone.addEventListener('dragleave', () => dropzone.classList.remove('is-dragging'));
    dropzone.addEventListener('drop', (event) => { event.preventDefault(); dropzone.classList.remove('is-dragging'); add(Array.from(event.dataTransfer.files)); });
    clear.addEventListener('click', () => { entries = []; status.textContent = ''; renderQueue(); });
    const send = (entry) => new Promise((resolve) => {
        const data = new FormData(); data.append('_token', form.elements._token.value); data.append('category', form.elements.category.value); data.append('asset', entry.file); data.append('upload_key', entry.key);
        const xhr = new XMLHttpRequest(); xhr.open('POST', form.dataset.itemUrl); xhr.timeout = 120000; xhr.setRequestHeader('Accept', 'application/json');
        xhr.upload.addEventListener('progress', (event) => { if (event.lengthComputable) { entry.percent = Math.round(event.loaded / event.total * 100); entry.message = entry.percent === 100 ? 'Przetwarzanie grafiki…' : `Wgrywanie: ${entry.percent}%`; renderQueue(); } });
        const failed = (message) => { entry.state = 'error'; entry.message = message; renderQueue(); resolve(); };
        xhr.addEventListener('load', () => {
            let result; try { result = JSON.parse(xhr.responseText); } catch { failed('Brak potwierdzenia serwera. Sprawdź bibliotekę przed ponowieniem.'); return; }
            if (xhr.status >= 200 && xhr.status < 300 && result.ok === true) {
                entry.id = result.id; entry.state = 'done'; entry.percent = 100; entry.message = 'Dodano do biblioteki';
                if (typeof result.url === 'string' && result.url.startsWith('/uploads/media/')) { entry.preview = result.url; entry.serverPreview = true; }
                renderQueue(); resolve();
            }
            else failed(typeof result.message === 'string' ? result.message : 'Nie udało się dodać pliku.');
        });
        xhr.addEventListener('error', () => failed('Błąd połączenia. Sprawdź bibliotekę przed ponowieniem.'));
        xhr.addEventListener('timeout', () => failed('Upłynął czas. Sprawdź bibliotekę przed ponowieniem.'));
        xhr.send(data);
    });
    form.addEventListener('submit', async (event) => {
        event.preventDefault(); if (running || entries.length === 0) return;
        running = true; renderQueue();
        for (const entry of entries.filter((item) => item.state !== 'done')) {
            entry.state = 'uploading'; entry.message = 'Wgrywanie…'; entry.percent = 0; renderQueue(); await send(entry);
            status.textContent = `Dodano: ${entries.filter((item) => item.state === 'done').length} z ${entries.length}.`;
        }
        running = false; renderQueue();
        const count = entries.filter((entry) => entry.state === 'error').length;
        status.textContent += count ? ` Nieudane: ${count}. Możesz ponowić te pliki.` : ' Gotowe. Przejdź do grafik, aby uzupełnić nazwy, kategorie i opisy.';
    });
    window.addEventListener('beforeunload', (event) => { if (running || entries.some((entry) => entry.state !== 'done')) { event.preventDefault(); event.returnValue = ''; } });
    renderQueue();
})();
