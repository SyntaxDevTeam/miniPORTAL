(() => {
  const root = document.querySelector("[data-terminal]");
  const screen = root?.querySelector("[data-terminal-screen]");
  const status = root?.querySelector("[data-status]");
  if (!(root instanceof HTMLElement) || !(screen instanceof HTMLElement) || !(status instanceof HTMLElement)) return;
  if (typeof window.Terminal !== "function" || !window.FitAddon?.FitAddon) return;

  const terminalCols = Number(root.dataset.cols) || 120;
  const terminalRows = Number(root.dataset.rows) || 32;
  const outputUrl = root.dataset.outputUrl || "";
  const inputUrl = root.dataset.inputUrl || "";
  const token = root.dataset.token || "";
  const csrf = root.dataset.csrf || "";
  const term = new window.Terminal({
    cols: terminalCols,
    rows: terminalRows,
    cursorBlink: true,
    convertEol: false,
    scrollback: 5000,
    fontFamily: "ui-monospace, SFMono-Regular, Menlo, Consolas, monospace",
    fontSize: 15,
    lineHeight: 1.1,
    theme: {
      background: "#020617", foreground: "#d1fae5", cursor: "#67e8f9",
      selectionBackground: "#334155", black: "#0f172a", red: "#ef4444",
      green: "#22c55e", yellow: "#eab308", blue: "#38bdf8", magenta: "#c084fc",
      cyan: "#67e8f9", white: "#e2e8f0", brightBlack: "#475569",
      brightRed: "#f87171", brightGreen: "#86efac", brightYellow: "#fde047",
      brightBlue: "#7dd3fc", brightMagenta: "#d8b4fe", brightCyan: "#a5f3fc",
      brightWhite: "#f8fafc",
    },
  });
  const fitAddon = new window.FitAddon.FitAddon();
  term.loadAddon(fitAddon);
  term.open(screen);

  const fit = () => {
    fitAddon.fit();
    if (term.rows > terminalRows) term.resize(term.cols, terminalRows);
    term.focus();
  };
  requestAnimationFrame(fit);
  window.setTimeout(fit, 100);
  window.setTimeout(fit, 350);
  window.addEventListener("resize", () => requestAnimationFrame(fit));

  let offset = 0;
  let polling = true;
  let pollTimer = 0;
  let pollController = null;
  let pollGeneration = 0;
  let pollEtag = "";
  let idlePolls = 0;
  let running = true;
  let pendingInput = "";
  let inputTimer = 0;

  const pollDelay = () => {
    if (document.hidden) return 10000;
    const base = running ? 750 : 2000;
    const maximum = running ? 4000 : 10000;
    return Math.min(maximum, Math.round(base * (1.45 ** Math.min(idlePolls, 6))));
  };

  const schedulePoll = () => {
    if (!polling) return;
    window.clearTimeout(pollTimer);
    pollTimer = window.setTimeout(runPoll, pollDelay());
  };

  const runPoll = async () => {
    if (!polling) return;
    const generation = ++pollGeneration;
    pollController?.abort();
    const controller = new AbortController();
    pollController = controller;
    try {
      const url = `${outputUrl}&token=${encodeURIComponent(token)}&offset=${offset}`;
      const headers = { Accept: "application/json" };
      if (pollEtag) headers["X-Poll-ETag"] = pollEtag;
      const response = await fetch(url, { cache: "no-store", headers, signal: controller.signal });
      const responseEtag = response.headers.get("ETag");
      if (responseEtag) pollEtag = responseEtag;
      if (response.status === 204) {
        idlePolls += 1;
        schedulePoll();
        return;
      }
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      const data = await response.json();
      const nextRunning = data.running === true;
      const changed = Boolean(data.output) || Boolean(data.reset) || nextRunning !== running;
      running = nextRunning;
      offset = Number.isFinite(Number(data.offset)) ? Number(data.offset) : offset;
      if (data.reset) term.reset();
      if (data.output) term.write(data.output, () => term.scrollToBottom());
      idlePolls = changed ? 0 : idlePolls + 1;
      status.textContent = running ? "Połączono z procesem SSH." : "Sesja zamknięta.";
      schedulePoll();
    } catch (error) {
      if (error?.name === "AbortError") return;
      idlePolls = Math.min(idlePolls + 2, 8);
      status.textContent = "Ponawiam połączenie...";
      schedulePoll();
    } finally {
      if (generation === pollGeneration) pollController = null;
    }
  };

  const wakePoll = () => {
    idlePolls = 0;
    pollEtag = "";
    window.clearTimeout(pollTimer);
    runPoll();
  };

  const send = async (value) => {
    if (!value) return;
    try {
      const response = await fetch(inputUrl, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ token, csrf_token: csrf, input: value }),
      });
      if (!response.ok) status.textContent = "Nie wysłano wejścia";
      else wakePoll();
    } catch {
      status.textContent = "Nie wysłano wejścia";
    }
  };

  const flushInput = () => {
    inputTimer = 0;
    const value = pendingInput;
    pendingInput = "";
    send(value);
  };
  term.onData((data) => {
    pendingInput += data;
    if (inputTimer === 0) inputTimer = window.setTimeout(flushInput, 12);
  });

  screen.addEventListener("click", () => term.focus());
  document.addEventListener("visibilitychange", () => {
    window.clearTimeout(pollTimer);
    if (!document.hidden) wakePoll();
    else schedulePoll();
  });
  window.addEventListener("beforeunload", () => {
    polling = false;
    pollController?.abort();
    window.clearTimeout(pollTimer);
  });
  runPoll();
})();
