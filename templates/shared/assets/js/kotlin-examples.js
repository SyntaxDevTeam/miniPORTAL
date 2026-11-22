(() => {
  'use strict';
  if (window.miniportalKotlinExamples) { window.miniportalKotlinExamples(); return; }
  const ENDPOINT = 'https://api.kotlinlang.org/api/2.4.21/compiler/run';
  const MAX_CODE = 50000, MAX_OUTPUT = 20000, REQUEST_TIMEOUT = 20000;
  let active = null;

  const button = label => {
    const node = document.createElement('button');
    node.type = 'button';
    node.textContent = label;
    return node;
  };
  const formatResult = data => {
    if (!data || typeof data !== 'object' || Array.isArray(data)) {
      throw new Error('Serwer kompilacji zwrócił nieprawidłową odpowiedź.');
    }
    const output = [];
    let failed = false;
    if (data.errors && typeof data.errors === 'object') {
      for (const [file, messages] of Object.entries(data.errors)) {
        if (!Array.isArray(messages)) continue;
        for (const diagnostic of messages.slice(0, 100)) {
          if (!diagnostic || typeof diagnostic !== 'object') continue;
          const severity = String(diagnostic.severity || 'ERROR').toUpperCase();
          if (severity === 'ERROR') failed = true;
          const line = diagnostic.interval?.start?.line;
          const column = diagnostic.interval?.start?.ch;
          const position = Number.isInteger(line) && Number.isInteger(column)
            ? ' (' + String(line + 1) + ':' + String(column + 1) + ')' : '';
          output.push(severity + ' ' + String(file).slice(0, 100) + position + ': ' + String(diagnostic.message || '').slice(0, 2000));
        }
      }
    }
    if (typeof data.text === 'string') {
      const stream = /<(outStream|errStream)>([\s\S]*?)<\/\1>/g;
      let index = 0, found = false, match;
      while ((match = stream.exec(data.text)) !== null) {
        if (match.index > index) output.push(data.text.slice(index, match.index));
        output.push(match[2]);
        index = stream.lastIndex;
        found = true;
      }
      if (!found) output.push(data.text);
      else if (index < data.text.length) output.push(data.text.slice(index));
    }
    if (data.exception && typeof data.exception === 'object') {
      failed = true;
      output.push(String(data.exception.fullName || 'Wyjątek') + ': ' + String(data.exception.message || 'Błąd wykonania'));
    }
    return { text: output.join('\n').slice(0, MAX_OUTPUT) || '(Brak wyniku)', failed };
  };

  const init = () => document.querySelectorAll('[data-code-examples] pre > code.language-kotlin-run').forEach(code => {
    const pre = code.parentElement;
    if (pre.dataset.kotlinReady) return;
    pre.dataset.kotlinReady = 'true';
    const original = code.textContent;
    const box = document.createElement('section');
    box.className = 'code-example';
    box.dataset.language = 'kotlin';
    box.setAttribute('aria-label', 'Interaktywny przykład Kotlin JVM');
    const toolbar = document.createElement('div');
    toolbar.className = 'code-example-toolbar';
    const title = document.createElement('strong');
    title.textContent = '⌘ Kotlin JVM';
    const actions = document.createElement('div');
    actions.className = 'code-example-actions';
    const reset = button('Przywróć'), copy = button('Kopiuj'), run = button('▷ Uruchom'), stop = button('Anuluj');
    stop.hidden = true;
    actions.append(reset, copy, run, stop);
    toolbar.append(title, actions);
    const source = document.createElement('textarea');
    source.className = 'code-example-source';
    source.value = original;
    source.rows = Math.max(5, Math.min(18, original.split('\n').length + 1));
    source.maxLength = MAX_CODE;
    source.spellcheck = false;
    source.setAttribute('aria-label', 'Kod Kotlin do uruchomienia');
    const status = document.createElement('p');
    status.className = 'code-example-status';
    status.setAttribute('role', 'status');
    status.textContent = 'Edytuj kod i kliknij Uruchom. Kod zostanie wysłany do serwera kompilacji JetBrains; miniPORTAL nie wykonuje go lokalnie.';
    const result = document.createElement('pre');
    result.className = 'code-example-output';
    result.setAttribute('aria-label', 'Wynik programu Kotlin');
    result.tabIndex = 0;
    result.hidden = true;
    box.append(toolbar, source, status, result);
    pre.replaceWith(box);
    const editor = window.CodeMirror?.fromTextArea(source, {
      mode: 'text/x-kotlin', lineNumbers: true, indentUnit: 4, tabSize: 4,
      viewportMargin: 20, lineWrapping: false
    });
    editor?.getInputField().setAttribute('aria-label', 'Kod Kotlin do uruchomienia');
    const getCode = () => editor ? editor.getValue() : source.value;
    const setCode = value => editor ? editor.setValue(value) : (source.value = value);
    let current = null;
    const finish = message => {
      if (current === null) return;
      const state = current;
      current = null;
      state.controller.abort();
      if (active === state) active = null;
      run.hidden = false;
      stop.hidden = true;
      box.removeAttribute('aria-busy');
      status.textContent = message;
    };
    reset.addEventListener('click', () => {
      finish('Przywrócono oryginalny przykład.');
      setCode(original);
      result.textContent = '';
      result.hidden = true;
      status.textContent = 'Przywrócono oryginalny przykład.';
    });
    copy.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(getCode());
        status.textContent = 'Kod skopiowany.';
      } catch {
        status.textContent = 'Zaznacz kod i użyj Ctrl+C lub ⌘C.';
        editor ? editor.focus() : source.focus();
      }
    });
    stop.addEventListener('click', () => finish('Anulowano żądanie. Kompilacja na serwerze JetBrains mogła już się rozpocząć.'));
    run.addEventListener('click', async () => {
      const code = getCode();
      if (code.length > MAX_CODE) {
        status.textContent = 'Kod może mieć maksymalnie 50 000 znaków.';
        return;
      }
      active?.finish('Anulowano poprzednie żądanie.');
      const state = { controller: new AbortController(), finish };
      current = state;
      active = state;
      run.hidden = true;
      stop.hidden = false;
      box.setAttribute('aria-busy', 'true');
      result.hidden = false;
      result.textContent = '';
      status.textContent = 'Kompilowanie Kotlin na serwerze JetBrains…';
      const timeout = setTimeout(() => {
        if (current === state) finish('Przekroczono limit oczekiwania (20 sekund).');
      }, REQUEST_TIMEOUT);
      try {
        const response = await fetch(ENDPOINT, {
          method: 'POST', mode: 'cors', credentials: 'omit', redirect: 'error',
          referrerPolicy: 'no-referrer', cache: 'no-store',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            args: '', files: [{ name: 'File.kt', text: code, publicId: '' }], confType: 'java'
          }),
          signal: state.controller.signal
        });
        if (!response.ok) throw new Error('HTTP ' + response.status);
        const length = Number(response.headers.get('content-length') || 0);
        if (length > 262144) throw new Error('Odpowiedź serwera jest za duża.');
        const payload = await response.text();
        if (payload.length > 262144) throw new Error('Odpowiedź serwera jest za duża.');
        const formatted = formatResult(JSON.parse(payload));
        if (current !== state) return;
        result.textContent = formatted.text;
        finish(formatted.failed ? 'Program zgłosił błędy.' : 'Zakończono.');
      } catch (error) {
        if (current !== state) return;
        result.textContent = 'Nie udało się uruchomić kodu Kotlin: ' + String(error.message || error).slice(0, 400);
        finish('Błąd połączenia lub środowiska kompilacji.');
      } finally {
        clearTimeout(timeout);
      }
    });
    if (editor) editor.addKeyMap({ 'Ctrl-Enter': () => run.click(), 'Cmd-Enter': () => run.click() });
  });
  window.miniportalKotlinExamples = init;
  document.addEventListener('htmx:afterSwap', init);
  document.addEventListener('htmx:beforeSwap', () => active?.finish('Anulowano żądanie przy zmianie strony.'));
  window.addEventListener('pagehide', () => active?.finish('Anulowano żądanie.'));
  init();
})();
