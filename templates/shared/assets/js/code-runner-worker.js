/* Trusted bridge runs only inside an opaque sandbox; CSP limits networking to interpreter assets. */
'use strict';
self.onmessage = async event => {
  self.onmessage = null; // One fresh interpreter per run, including after exceptions.
  const { code, stdin, runtimeBase } = event.data;
  const send = self.postMessage.bind(self);
  let size = 0;
  let inputExhausted = false;
  const output = text => {
    text = String(text);
    size += text.length;
    if (size > 20000) throw new Error('Przekroczono limit wyniku (20 000 znaków).');
    send({ type: 'output', text });
  };
  try {
    // Buffer the complete response before compilation. Some browsers abort a streamed
    // WASM response when an earlier opaque frame is destroyed between consecutive runs.
    WebAssembly.instantiateStreaming = async (response, imports) =>
      WebAssembly.instantiate(await (await response).arrayBuffer(), imports);
    importScripts(runtimeBase + 'pyodide.js');
    const inputs = stdin === '' ? [] : stdin.replace(/\r\n/g, '\n').split('\n');
    const pyodide = await loadPyodide({
      indexURL: runtimeBase,
      lockFileURL: runtimeBase + 'pyodide-lock.txt',
      stdout: () => {}, stderr: () => {},
      stdin: () => {
        if (!inputs.length) { inputExhausted = true; return null; }
        return inputs.shift();
      },
    });
    // Raw output keeps print(..., end='') and prompts; the parent also limits message volume.
    const decoder = new TextDecoder();
    const write = buffer => { output(decoder.decode(buffer, { stream: true })); return buffer.length; };
    pyodide.setStdout({ write });
    pyodide.setStderr({ write });
    send({ type: 'running' });
    await pyodide.runPythonAsync(code);
    output(decoder.decode());
    send({ type: 'done' });
  } catch (error) {
    send({ type: 'error', text: (inputExhausted ? 'Brak danych dla input(). Uzupełnij pole „Dane wejściowe”.\n' : '') + String(error).slice(-5000) });
  }
};
