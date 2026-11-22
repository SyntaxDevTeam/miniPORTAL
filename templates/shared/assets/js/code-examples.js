(() => {
  'use strict';
  if (window.miniportalCodeExamples) { window.miniportalCodeExamples(); return; }
  let active = null;
  const button = (text, label = text) => {
    const node = document.createElement('button');
    node.type = 'button'; node.textContent = text; node.setAttribute('aria-label', label);
    return node;
  };
  const init = () => document.querySelectorAll('[data-code-examples] pre > code.language-python-run').forEach(code => {
    const pre = code.parentElement;
    if (pre.dataset.pythonReady) return;
    pre.dataset.pythonReady = 'true';
    const original = code.textContent;
    const box = document.createElement('section'); box.className = 'code-example'; box.setAttribute('aria-label', 'Interaktywny przykład Python');
    const bar = document.createElement('div'); bar.className = 'code-example-toolbar';
    const title = document.createElement('strong'); title.textContent = '⌘ Python';
    const actions = document.createElement('div'); actions.className = 'code-example-actions';
    const reset = button('Przywróć'), copy = button('Kopiuj'), run = button('▷ Uruchom'), stop = button('Zatrzymaj');
    stop.hidden = true;
    actions.append(reset, copy, run, stop); bar.append(title, actions);
    const source = document.createElement('textarea'); source.value = original; source.className = 'code-example-source';
    source.setAttribute('aria-label', 'Kod Python do uruchomienia'); source.spellcheck = false; source.maxLength = 50000;
    source.rows = Math.max(5, Math.min(18, original.split('\n').length + 1));
    const inputs = document.createElement('details'); inputs.className = 'code-example-inputs';
    const summary = document.createElement('summary'); summary.textContent = 'Dane wejściowe dla input()';
    const stdin = document.createElement('textarea'); stdin.rows = 3; stdin.maxLength = 10000; stdin.placeholder = 'Każda odpowiedź w osobnej linii';
    stdin.setAttribute('aria-label', 'Dane wejściowe programu Python'); inputs.append(summary, stdin);
    const status = document.createElement('p'); status.className = 'code-example-status'; status.setAttribute('role', 'status');
    status.textContent = 'Edytuj kod i kliknij Uruchom. Pierwszy start pobierze lokalny silnik Python (około 13 MB).';
    const result = document.createElement('pre'); result.className = 'code-example-output'; result.setAttribute('aria-label', 'Wynik programu'); result.tabIndex = 0; result.hidden = true;
    box.append(bar, source, inputs, status, result); pre.replaceWith(box);
    const editor = window.CodeMirror?.fromTextArea(source, { mode: 'python', lineNumbers: true, indentUnit: 4, tabSize: 4, viewportMargin: 20, lineWrapping: false });
    editor?.getInputField().setAttribute('aria-label', 'Kod Python do uruchomienia');
    const getCode = () => editor ? editor.getValue() : source.value;
    const setCode = value => editor ? editor.setValue(value) : (source.value = value);
    let frame = null, port = null, timer = null;
    let messages = 0, started = false;
    const finish = message => {
      clearTimeout(timer); timer = null;
      if (port) { port.onmessage = null; port.close(); port = null; }
      frame?.remove(); frame = null;
      run.hidden = false; stop.hidden = true; box.removeAttribute('aria-busy');
      if (active?.box === box) active = null;
      status.textContent = message;
    };
    reset.addEventListener('click', () => { finish('Przywrócono oryginalny przykład.'); setCode(original); stdin.value = ''; result.textContent = ''; result.hidden = true; });
    copy.addEventListener('click', async () => {
      try { await navigator.clipboard.writeText(getCode()); status.textContent = 'Kod skopiowany.'; }
      catch { status.textContent = 'Zaznacz kod i użyj Ctrl+C lub ⌘C.'; editor ? editor.focus() : source.focus(); }
    });
    stop.addEventListener('click', () => finish('Zatrzymano program.'));
    run.addEventListener('click', () => {
      const value = getCode();
      if (value.length > 50000) { status.textContent = 'Kod może mieć maksymalnie 50 000 znaków.'; return; }
      active?.finish('Zatrzymano poprzedni przykład.');
      active = { box, finish };
      messages = 0; started = false; result.textContent = ''; result.hidden = false;
      run.hidden = true; stop.hidden = false; box.setAttribute('aria-busy', 'true');
      status.textContent = 'Ładowanie środowiska Python…';
      frame = document.createElement('iframe'); frame.hidden = true; frame.title = 'Odizolowane środowisko Python';
      frame.setAttribute('sandbox', 'allow-scripts'); frame.setAttribute('referrerpolicy', 'no-referrer');
      frame.src = '/index.php?route=/articles/code-runner';
      frame.onload = () => {
        const channel = new MessageChannel(); port = channel.port1;
        port.onmessage = event => {
          const data = event.data;
          if (!data || !['connected', 'running', 'output', 'done', 'error'].includes(data.type)) return;
          if (++messages > 3000) { finish('Przekroczono limit komunikatów programu.'); return; }
          if (data.type === 'connected') port.postMessage({ code: value, stdin: stdin.value });
          if (data.type === 'running' && !started) {
            started = true;
            clearTimeout(timer); timer = setTimeout(() => finish('Przekroczono limit czasu (10 sekund).'), 10000);
            status.textContent = 'Program działa…';
          }
          if (data.type === 'output' && typeof data.text === 'string') {
            if (result.textContent.length + data.text.length > 20000) { finish('Przekroczono limit wyniku (20 000 znaków).'); return; }
            result.append(document.createTextNode(data.text));
          }
          if (data.type === 'done') finish('Zakończono.');
          if (data.type === 'error') {
            if (typeof data.text === 'string') result.append(document.createTextNode(('\n' + data.text.slice(0, 5000)).slice(0, Math.max(0, 20000 - result.textContent.length))));
            finish('Błąd programu lub środowiska Python.');
          }
        };
        port.start();
        frame.contentWindow.postMessage('python-connect', '*', [channel.port2]);
      };
      timer = setTimeout(() => finish('Nie udało się załadować środowiska w ciągu 60 sekund. Spróbuj ponownie.'), 60000);
      box.append(frame);
    });
    if (editor) editor.addKeyMap({ 'Ctrl-Enter': () => run.click(), 'Cmd-Enter': () => run.click() });
  });
  window.miniportalCodeExamples = init;
  document.addEventListener('htmx:afterSwap', init);
  document.addEventListener('htmx:beforeSwap', () => active?.finish('Zatrzymano program przy zmianie strony.'));
  window.addEventListener('pagehide', () => active?.finish('Zatrzymano program.'));
  init();
})();
