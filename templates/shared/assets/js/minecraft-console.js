(() => {
  "use strict";

  const MAX_OUTPUT_LENGTH = 262144;
  const COLOR_NAMES = [
    "black", "red", "green", "yellow", "blue", "magenta", "cyan", "white",
    "bright-black", "bright-red", "bright-green", "bright-yellow",
    "bright-blue", "bright-magenta", "bright-cyan", "bright-white",
  ];
  const COLOR_RGB = [
    [0, 0, 0], [170, 0, 0], [0, 170, 0], [170, 85, 0],
    [0, 0, 170], [170, 0, 170], [0, 170, 170], [170, 170, 170],
    [85, 85, 85], [255, 85, 85], [85, 255, 85], [255, 255, 85],
    [85, 85, 255], [255, 85, 255], [85, 255, 255], [255, 255, 255],
  ];
  const MINECRAFT_COLORS = {
    0: 0, 1: 4, 2: 2, 3: 6, 4: 1, 5: 5, 6: 3, 7: 7,
    8: 8, 9: 12, a: 10, b: 14, c: 9, d: 13, e: 11, f: 15,
  };

  document.querySelectorAll(".minecraft-file-modal").forEach((modal) => {
    if (modal.parentElement !== document.body) {
      document.body.append(modal);
    }
  });

  const newState = () => ({
    foreground: null,
    background: null,
    bold: false,
    dim: false,
    italic: false,
    underline: false,
    strike: false,
    inverse: false,
  });

  const resetState = (state) => Object.assign(state, newState());

  const nearestColor = (red, green, blue) => {
    let nearest = 0;
    let distance = Number.POSITIVE_INFINITY;
    COLOR_RGB.forEach((candidate, index) => {
      const nextDistance = ((red - candidate[0]) ** 2)
        + ((green - candidate[1]) ** 2)
        + ((blue - candidate[2]) ** 2);
      if (nextDistance < distance) {
        distance = nextDistance;
        nearest = index;
      }
    });
    return nearest;
  };

  const ansi256Color = (value) => {
    if (value < 16) return value;
    if (value >= 232) {
      const gray = 8 + ((value - 232) * 10);
      return nearestColor(gray, gray, gray);
    }
    const offset = value - 16;
    const levels = [0, 95, 135, 175, 215, 255];
    return nearestColor(
      levels[Math.floor(offset / 36) % 6],
      levels[Math.floor(offset / 6) % 6],
      levels[offset % 6],
    );
  };

  const applyExtendedColor = (state, parameters, index, target) => {
    const mode = parameters[index + 1];
    if (mode === 5 && Number.isInteger(parameters[index + 2])) {
      state[target] = ansi256Color(Math.max(0, Math.min(255, parameters[index + 2])));
      return index + 2;
    }
    if (mode === 2 && parameters.slice(index + 2, index + 5).every(Number.isInteger)) {
      state[target] = nearestColor(
        Math.max(0, Math.min(255, parameters[index + 2])),
        Math.max(0, Math.min(255, parameters[index + 3])),
        Math.max(0, Math.min(255, parameters[index + 4])),
      );
      return index + 4;
    }
    return index;
  };

  const applySgr = (state, source) => {
    const parameters = source === ""
      ? [0]
      : source.split(/[;:]/).map((value) => value === "" ? 0 : Number.parseInt(value, 10));

    for (let index = 0; index < parameters.length; index += 1) {
      const code = parameters[index];
      if (!Number.isInteger(code) || code === 0) {
        if (code === 0) resetState(state);
      } else if (code === 1) {
        state.bold = true;
      } else if (code === 2) {
        state.dim = true;
      } else if (code === 3) {
        state.italic = true;
      } else if (code === 4) {
        state.underline = true;
      } else if (code === 7) {
        state.inverse = true;
      } else if (code === 9) {
        state.strike = true;
      } else if (code === 22) {
        state.bold = false;
        state.dim = false;
      } else if (code === 23) {
        state.italic = false;
      } else if (code === 24) {
        state.underline = false;
      } else if (code === 27) {
        state.inverse = false;
      } else if (code === 29) {
        state.strike = false;
      } else if (code >= 30 && code <= 37) {
        state.foreground = code - 30;
      } else if (code >= 90 && code <= 97) {
        state.foreground = code - 90 + 8;
      } else if (code >= 40 && code <= 47) {
        state.background = code - 40;
      } else if (code >= 100 && code <= 107) {
        state.background = code - 100 + 8;
      } else if (code === 38 || code === 48) {
        index = applyExtendedColor(
          state,
          parameters,
          index,
          code === 38 ? "foreground" : "background",
        );
      } else if (code === 39) {
        state.foreground = null;
      } else if (code === 49) {
        state.background = null;
      }
    }
  };

  const applyMinecraftCode = (state, source, offset) => {
    const code = source[offset + 1]?.toLowerCase();
    if (Object.prototype.hasOwnProperty.call(MINECRAFT_COLORS, code)) {
      resetState(state);
      state.foreground = MINECRAFT_COLORS[code];
      return offset + 2;
    }
    if (code === "x" && source.length >= offset + 14) {
      let hex = "";
      for (let index = offset + 2; index < offset + 14; index += 2) {
        if (source[index] !== "§" || !/[0-9a-f]/i.test(source[index + 1] || "")) return offset;
        hex += source[index + 1];
      }
      resetState(state);
      state.foreground = nearestColor(
        Number.parseInt(hex.slice(0, 2), 16),
        Number.parseInt(hex.slice(2, 4), 16),
        Number.parseInt(hex.slice(4, 6), 16),
      );
      return offset + 14;
    }
    if (code === "l") state.bold = true;
    else if (code === "m") state.strike = true;
    else if (code === "n") state.underline = true;
    else if (code === "o") state.italic = true;
    else if (code === "r") resetState(state);
    else return offset;
    return offset + 2;
  };

  const stateClasses = (state) => {
    const classes = [];
    if (state.foreground !== null) classes.push(`minecraft-console__fg-${COLOR_NAMES[state.foreground]}`);
    if (state.background !== null) classes.push(`minecraft-console__bg-${COLOR_NAMES[state.background]}`);
    if (state.bold) classes.push("minecraft-console__bold");
    if (state.dim) classes.push("minecraft-console__dim");
    if (state.italic) classes.push("minecraft-console__italic");
    if (state.underline) classes.push("minecraft-console__underline");
    if (state.strike) classes.push("minecraft-console__strike");
    if (state.inverse) classes.push("minecraft-console__inverse");
    return classes;
  };

  const renderConsoleOutput = (element, source) => {
    const fragment = document.createDocumentFragment();
    const state = newState();
    let buffer = "";

    const flush = () => {
      if (buffer === "") return;
      const classes = stateClasses(state);
      if (classes.length === 0) {
        fragment.append(document.createTextNode(buffer));
      } else {
        const span = document.createElement("span");
        span.classList.add(...classes);
        span.textContent = buffer;
        fragment.append(span);
      }
      buffer = "";
    };

    for (let index = 0; index < source.length;) {
      const character = source[index];
      if (character === "\u001b") {
        flush();
        if (source[index + 1] === "[") {
          let end = index + 2;
          while (end < source.length && end - index < 96 && !/[@-~]/.test(source[end])) end += 1;
          if (end < source.length && source[end] === "m") {
            applySgr(state, source.slice(index + 2, end));
          }
          index = end < source.length ? end + 1 : source.length;
          continue;
        }
        if (source[index + 1] === "]") {
          let end = index + 2;
          while (
            end < source.length
            && source[end] !== "\u0007"
            && !(source[end] === "\u001b" && source[end + 1] === "\\")
          ) end += 1;
          index = end + (source[end] === "\u001b" ? 2 : 1);
          continue;
        }
        index += 2;
        continue;
      }
      if (character === "§") {
        flush();
        const nextIndex = applyMinecraftCode(state, source, index);
        if (nextIndex !== index) {
          index = nextIndex;
          continue;
        }
      }
      if (character === "\b") {
        buffer = buffer.slice(0, -1);
        index += 1;
        continue;
      }
      if (character < " " && character !== "\n" && character !== "\t") {
        index += 1;
        continue;
      }
      buffer += character;
      index += 1;
    }
    flush();
    element.replaceChildren(fragment);
  };

  document.querySelectorAll("[data-minecraft-console]").forEach((root) => {
    const output = root.querySelector("[data-console-output]");
    const form = root.querySelector("[data-console-form]");
    if (!(output instanceof HTMLElement) || !(form instanceof HTMLFormElement)) return;

    let pollTimer = null;
    let pollController = null;
    let pollGeneration = 0;
    let pollEtag = "";
    let idlePolls = 0;
    let consoleRunning = true;
    let polling = true;
    let rawOutput = "";
    let consoleOffset = 0;
    let streamInitialized = false;
    let hasRenderedOutput = false;

    const limitOutput = (source) => {
      if (source.length <= MAX_OUTPUT_LENGTH) return source;
      const trimmed = source.slice(-MAX_OUTPUT_LENGTH);
      const firstLineBreak = trimmed.indexOf("\n");
      return firstLineBreak >= 0 ? trimmed.slice(firstLineBreak + 1) : trimmed;
    };

    const applyConsoleOutput = (nextOutput, forceBottom = false) => {
      if (nextOutput === rawOutput && hasRenderedOutput) return;
      const stickToBottom = output.scrollHeight - output.scrollTop - output.clientHeight < 32;
      const previousScrollTop = output.scrollTop;
      rawOutput = limitOutput(nextOutput);
      renderConsoleOutput(output, rawOutput);
      hasRenderedOutput = true;
      if (stickToBottom || forceBottom) output.scrollTop = output.scrollHeight;
      else output.scrollTop = previousScrollTop;
    };

    const showPlainMessage = (message) => {
      rawOutput = "";
      hasRenderedOutput = false;
      output.textContent = message;
    };

    const pollDelay = () => {
      if (document.hidden) return 10000;
      const base = consoleRunning ? 1200 : 2500;
      const maximum = consoleRunning ? 5000 : 10000;
      return Math.min(maximum, Math.round(base * (1.45 ** Math.min(idlePolls, 6))));
    };

    const schedulePoll = () => {
      if (!polling) return;
      window.clearTimeout(pollTimer);
      pollTimer = window.setTimeout(poll, pollDelay());
    };

    const poll = async () => {
      if (!polling) return;
      const generation = ++pollGeneration;
      pollController?.abort();
      const controller = new AbortController();
      pollController = controller;
      try {
        const url = new URL(root.dataset.outputUrl || "", window.location.href);
        url.searchParams.set("offset", String(consoleOffset));
        const headers = { Accept: "application/json" };
        if (pollEtag) headers["X-Poll-ETag"] = pollEtag;
        const response = await fetch(url.toString(), {
          cache: "no-store",
          headers,
          signal: controller.signal,
        });
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
        const runningChanged = nextRunning !== consoleRunning;
        consoleRunning = nextRunning;
        let outputChanged = false;
        if (data.source === "log") {
          const nextChunk = typeof data.output === "string" ? data.output : "";
          const shouldReset = Boolean(data.reset) || !streamInitialized;
          outputChanged = nextChunk !== "" || shouldReset;
          applyConsoleOutput(shouldReset ? nextChunk : rawOutput + nextChunk, shouldReset);
          consoleOffset = Number.isFinite(Number(data.offset)) ? Number(data.offset) : consoleOffset;
          streamInitialized = true;
        } else {
          const nextOutput = typeof data.output === "string" ? data.output : "";
          outputChanged = nextOutput !== rawOutput;
          streamInitialized = false;
          consoleOffset = 0;
          applyConsoleOutput(nextOutput);
        }
        idlePolls = outputChanged || runningChanged ? 0 : idlePolls + 1;
        schedulePoll();
      } catch (error) {
        if (error?.name === "AbortError") return;
        showPlainMessage("Nie udało się pobrać bufora konsoli.");
        consoleOffset = 0;
        streamInitialized = false;
        pollEtag = "";
        idlePolls = Math.min(idlePolls + 2, 8);
        schedulePoll();
      } finally {
        if (generation === pollGeneration) pollController = null;
      }
    };

    const wakePoll = () => {
      idlePolls = 0;
      pollEtag = "";
      window.clearTimeout(pollTimer);
      poll();
    };

    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      const body = new FormData(form);
      try {
        const response = await fetch(root.dataset.commandUrl || "", { method: "POST", body });
        const command = form.elements.namedItem("command");
        if (response.ok && command instanceof HTMLInputElement) command.value = "";
        wakePoll();
      } catch {
        showPlainMessage("Nie udało się wysłać komendy do konsoli.");
      }
    });

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
    poll();
  });

  document.querySelectorAll("[data-minecraft-metrics]").forEach((root) => {
    const histories = { cpu: [], memory: [] };
    let timer = null;
    let controller = null;

    const formatBytes = (bytes) => {
      const units = ["B", "KB", "MB", "GB", "TB"];
      let value = Math.max(0, Number(bytes) || 0);
      let unit = 0;
      while (value >= 1024 && unit < units.length - 1) { value /= 1024; unit += 1; }
      return `${value.toLocaleString("pl-PL", { maximumFractionDigits: 1 })} ${units[unit]}`;
    };
    const updateMetric = (key, value, detail, percent) => {
      const valueNode = root.querySelector(`[data-metric-value="${key}"]`);
      const detailNode = root.querySelector(`[data-metric-detail="${key}"]`);
      const bar = root.querySelector(`[data-metric-bar="${key}"]`);
      if (valueNode) valueNode.textContent = value;
      if (detailNode) detailNode.textContent = detail;
      if (bar) bar.style.width = `${Math.max(0, Math.min(100, percent || 0))}%`;
    };
    const draw = (key, value, label) => {
      if (!Number.isFinite(value)) return;
      const history = histories[key];
      history.push(value);
      if (history.length > 40) history.shift();
      const svg = root.querySelector(`[data-metric-chart="${key}"]`);
      if (!svg) return;
      const maximum = 100;
      const points = history.map((item, index) => {
        const x = history.length === 1 ? 0 : (index / (history.length - 1)) * 360;
        return `${x.toFixed(1)},${(115 - (Math.min(100, item) / maximum) * 105).toFixed(1)}`;
      }).join(" ");
      const line = svg.querySelector("[data-chart-line]");
      const area = svg.querySelector("[data-chart-area]");
      if (line) line.setAttribute("points", points);
      if (area) area.setAttribute("d", points ? `M0 120 L${points.replaceAll(" ", " L")} L360 120 Z` : "");
      const current = root.querySelector(`[data-chart-current="${key}"]`);
      if (current) current.textContent = label;
    };
    const poll = async () => {
      controller?.abort();
      controller = new AbortController();
      try {
        const response = await fetch(root.dataset.metricsUrl || "", { cache: "no-store", headers: { Accept: "application/json" }, signal: controller.signal });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        const data = await response.json();
        const metrics = data.metrics || {};
        const cpu = Number(metrics.cpu_percent);
        const memory = Number(metrics.memory_bytes);
        const diskPercent = Number(metrics.disk_percent);
        updateMetric("cpu", Number.isFinite(cpu) ? `${cpu.toLocaleString("pl-PL")}%` : "Pomiar…", `${metrics.processes || 0} procesów w drzewie serwera`, cpu);
        const memoryTotal = Number(metrics.memory_total_bytes);
        const memoryPercent = memoryTotal > 0 ? memory / memoryTotal * 100 : 0;
        updateMetric("memory", formatBytes(memory), `${memoryPercent.toLocaleString("pl-PL", { maximumFractionDigits: 1 })}% RAM hosta · RSS procesu`, memoryPercent);
        updateMetric("disk", Number.isFinite(diskPercent) ? `${diskPercent.toLocaleString("pl-PL")}%` : "—", `${formatBytes(metrics.disk_used_bytes)} / ${formatBytes(metrics.disk_total_bytes)}`, diskPercent);
        draw("cpu", cpu, Number.isFinite(cpu) ? `${cpu.toLocaleString("pl-PL")}%` : "Pomiar…");
        draw("memory", memoryPercent, `${formatBytes(memory)} · ${memoryPercent.toLocaleString("pl-PL", { maximumFractionDigits: 1 })}%`);
      } catch (error) {
        if (error?.name !== "AbortError") updateMetric("cpu", "Brak danych", "Nie udało się wykonać pomiaru", 0);
      } finally {
        timer = window.setTimeout(poll, document.hidden ? 30000 : 5000);
      }
    };
    document.addEventListener("visibilitychange", () => {
      window.clearTimeout(timer);
      if (!document.hidden) poll();
    });
    poll();
  });

  const imagePreviewModal = document.querySelector("#minecraft-image-preview-modal");
  const imagePreviewTarget = imagePreviewModal?.querySelector("[data-minecraft-image-preview-target]");
  const imagePreviewTitle = imagePreviewModal?.querySelector("[data-minecraft-image-preview-title]");

  document.querySelectorAll("[data-minecraft-image-preview]").forEach((link) => {
    link.addEventListener("click", (event) => {
      if (!(imagePreviewModal instanceof HTMLElement) || !(imagePreviewTarget instanceof HTMLImageElement)) return;
      event.preventDefault();
      imagePreviewTarget.src = link.href;
      imagePreviewTarget.alt = `Podgląd pliku ${link.dataset.imageName || "graficznego"}`;
      if (imagePreviewTitle instanceof HTMLElement) {
        imagePreviewTitle.textContent = link.dataset.imageName || "Podgląd obrazu";
      }
      window.bootstrap?.Modal.getOrCreateInstance(imagePreviewModal).show();
    });
  });

  imagePreviewModal?.addEventListener("hidden.bs.modal", () => {
    if (!(imagePreviewTarget instanceof HTMLImageElement)) return;
    imagePreviewTarget.removeAttribute("src");
    imagePreviewTarget.alt = "";
  });

  const renameModal = document.querySelector("#minecraft-file-rename-modal");
  const renameSource = renameModal?.querySelector("[data-minecraft-file-rename-source]");
  const renameInput = renameModal?.querySelector("[data-minecraft-file-rename-input]");

  document.querySelectorAll("[data-minecraft-file-rename]").forEach((button) => {
    button.addEventListener("click", () => {
      const name = button.dataset.name || "";
      if (renameSource instanceof HTMLInputElement) renameSource.value = name;
      if (renameInput instanceof HTMLInputElement) renameInput.value = name;
    });
  });

  renameModal?.addEventListener("shown.bs.modal", () => {
    if (!(renameInput instanceof HTMLInputElement)) return;
    renameInput.focus();
    const dot = renameInput.value.lastIndexOf(".");
    const selectionEnd = dot > 0 ? dot : renameInput.value.length;
    renameInput.setSelectionRange(0, selectionEnd);
  });

  const appendYamlToken = (target, type, value) => {
    if (value === "") return;
    if (type === "") {
      target.append(document.createTextNode(value));
      return;
    }
    const token = document.createElement("span");
    token.className = `minecraft-yaml-token--${type}`;
    token.textContent = value;
    target.append(token);
  };

  const yamlMappingKey = (line) => {
    let quote = "";
    let keyStart = line.search(/\S/);
    if (keyStart < 0) return null;
    if (line[keyStart] === "-" && /\s/.test(line[keyStart + 1] || "")) {
      keyStart += 1;
      while (/\s/.test(line[keyStart] || "")) keyStart += 1;
    }
    for (let index = keyStart; index < line.length; index += 1) {
      const character = line[index];
      if (quote !== "") {
        if (quote === '"' && character === "\\") {
          index += 1;
        } else if (character === quote) {
          if (quote === "'" && line[index + 1] === "'") index += 1;
          else quote = "";
        }
        continue;
      }
      if (character === '"' || character === "'") {
        quote = character;
        continue;
      }
      if (character === "#" && (index === 0 || /\s/.test(line[index - 1]))) return null;
      if (character === ":" && (index + 1 === line.length || /[\s\[\]{},]/.test(line[index + 1]))) {
        return index > keyStart ? { start: keyStart, end: index } : null;
      }
    }
    return null;
  };

  const appendYamlValue = (target, value) => {
    let index = 0;
    while (index < value.length) {
      const character = value[index];
      if (/\s/.test(character)) {
        const start = index;
        while (index < value.length && /\s/.test(value[index])) index += 1;
        appendYamlToken(target, "", value.slice(start, index));
        continue;
      }
      if (character === "#" && (index === 0 || /\s/.test(value[index - 1]))) {
        appendYamlToken(target, "comment", value.slice(index));
        break;
      }
      if (character === '"' || character === "'") {
        const quote = character;
        const start = index;
        index += 1;
        while (index < value.length) {
          if (quote === '"' && value[index] === "\\") {
            index += 2;
            continue;
          }
          if (value[index] === quote) {
            if (quote === "'" && value[index + 1] === "'") {
              index += 2;
              continue;
            }
            index += 1;
            break;
          }
          index += 1;
        }
        appendYamlToken(target, "string", value.slice(start, index));
        continue;
      }
      if (/[&*!]/.test(character)) {
        const match = value.slice(index).match(/^[&*!][^\s\[\]{},:#]+/);
        if (match) {
          appendYamlToken(target, "anchor", match[0]);
          index += match[0].length;
          continue;
        }
      }
      const number = value.slice(index).match(/^[-+]?(?:0x[\da-f]+|0o[0-7]+|(?:\d+(?:\.\d*)?|\.\d+)(?:e[-+]?\d+)?)(?=$|[\s,\]}#])/i);
      if (number) {
        appendYamlToken(target, "number", number[0]);
        index += number[0].length;
        continue;
      }
      const keyword = value.slice(index).match(/^(?:true|false|null|yes|no|on|off|~)(?=$|[\s,\]}#])/i);
      if (keyword) {
        appendYamlToken(target, "keyword", keyword[0]);
        index += keyword[0].length;
        continue;
      }
      if (/[\[\]{},:|>%-]/.test(character)) {
        appendYamlToken(target, "punctuation", character);
        index += 1;
        continue;
      }
      const start = index;
      while (index < value.length && !/[\s'"&*!\[\]{},:|>%-]/.test(value[index])) index += 1;
      if (index === start) index += 1;
      appendYamlToken(target, "", value.slice(start, index));
    }
  };

  const highlightYaml = (value, target) => {
    const fragment = document.createDocumentFragment();
    const rows = value.split("\n");
    rows.forEach((line, row) => {
      const key = yamlMappingKey(line);
      if (key === null) {
        appendYamlValue(fragment, line);
      } else {
        appendYamlToken(fragment, "", line.slice(0, key.start));
        appendYamlToken(fragment, "key", line.slice(key.start, key.end));
        appendYamlToken(fragment, "punctuation", ":");
        appendYamlValue(fragment, line.slice(key.end + 1));
      }
      if (row < rows.length - 1) fragment.append(document.createTextNode("\n"));
    });
    target.replaceChildren(fragment);
  };

  const highlightLog = (value, target) => {
    const fragment = document.createDocumentFragment();
    const rows = value.split("\n");
    const errorPattern = /(?:\b(?:ERROR|SEVERE|FATAL)\b|\bCaused by:|\b[\w.$]+(?:Exception|Error):)/i;
    const warningPattern = /\bWARN(?:ING)?\b/i;
    rows.forEach((line, row) => {
      const severity = errorPattern.test(line) ? "error" : (warningPattern.test(line) ? "warning" : "");
      if (severity === "") {
        fragment.append(document.createTextNode(line));
      } else {
        const span = document.createElement("span");
        span.className = `minecraft-log-line--${severity}`;
        span.textContent = line;
        fragment.append(span);
      }
      if (row < rows.length - 1) fragment.append(document.createTextNode("\n"));
    });
    target.replaceChildren(fragment);
  };

  document.querySelectorAll("[data-minecraft-editor-form]").forEach((form) => {
    const editor = form.querySelector("[data-minecraft-editor]");
    const lines = form.querySelector("[data-minecraft-editor-lines]");
    const highlight = form.querySelector("[data-minecraft-editor-highlight]");
    const yamlValidation = form.querySelector("[data-minecraft-yaml-validation]");
    const status = form.querySelector("[data-minecraft-editor-status]");
    const position = form.querySelector("[data-minecraft-editor-position]");
    if (!(form instanceof HTMLFormElement) || !(editor instanceof HTMLTextAreaElement)) return;

    const initialValue = editor.value;
    let dirty = false;
    let highlightFrame = 0;
    let validationTimer = 0;

    const updateLines = () => {
      if (!(lines instanceof HTMLElement)) return;
      const count = Math.max(1, editor.value.split("\n").length);
      lines.textContent = Array.from({ length: count }, (_value, index) => String(index + 1)).join("\n");
    };

    const updatePosition = () => {
      if (!(position instanceof HTMLElement)) return;
      const before = editor.value.slice(0, editor.selectionStart ?? 0);
      const split = before.split("\n");
      position.textContent = `Ln ${split.length}, Col ${(split.at(-1)?.length || 0) + 1}`;
    };

    const updateDirty = () => {
      dirty = editor.value !== initialValue;
      if (!(status instanceof HTMLElement)) return;
      status.textContent = dirty ? "Niezapisane zmiany" : "Zapisano";
      status.classList.toggle("is-dirty", dirty);
    };

    const syncHighlightScroll = () => {
      if (!(highlight instanceof HTMLElement)) return;
      highlight.scrollTop = editor.scrollTop;
      highlight.scrollLeft = editor.scrollLeft;
    };

    const updateHighlight = () => {
      const language = editor.dataset.editorLanguage;
      if (!(["yaml", "log"].includes(language)) || !(highlight instanceof HTMLElement)) return;
      const surface = editor.closest(".minecraft-code-editor__surface");
      const limit = language === "log" ? 2097152 : 524288;
      if (new Blob([editor.value]).size > limit) {
        highlight.replaceChildren();
        surface?.classList.remove("has-syntax-highlighting");
        return;
      }
      if (language === "log") highlightLog(editor.value, highlight);
      else highlightYaml(editor.value, highlight);
      surface?.classList.add("has-syntax-highlighting");
      syncHighlightScroll();
    };

    const scheduleHighlight = () => {
      if (highlightFrame !== 0) return;
      highlightFrame = window.requestAnimationFrame(() => {
        highlightFrame = 0;
        updateHighlight();
      });
    };

    const yamlErrorLabel = (reason) => {
      const translations = [
        [/duplicated mapping key/i, "powtórzony klucz"],
        [/bad indentation/i, "nieprawidłowe wcięcie"],
        [/missed comma/i, "brak przecinka"],
        [/flow collection/i, "nieprawidłowy lub niedomknięty nawias"],
        [/unexpected end/i, "nieoczekiwany koniec pliku"],
        [/implicit mapping pair/i, "nieprawidłowa para klucz–wartość"],
        [/document separator is expected/i, "nieoczekiwana treść"],
      ];
      return translations.find(([pattern]) => pattern.test(reason))?.[1] || "nieprawidłowa składnia";
    };

    const validateYaml = () => {
      if (editor.dataset.editorLanguage !== "yaml" || !(yamlValidation instanceof HTMLElement)) return;
      yamlValidation.classList.remove("is-checking", "is-valid", "is-invalid", "is-skipped");
      yamlValidation.removeAttribute("aria-busy");
      yamlValidation.removeAttribute("title");
      if (new Blob([editor.value]).size > 524288) {
        yamlValidation.textContent = "Walidacja YAML wyłączona powyżej 512 KiB";
        yamlValidation.classList.add("is-skipped");
        return;
      }
      if (typeof window.jsyaml?.load !== "function") {
        yamlValidation.textContent = "Walidator YAML jest niedostępny";
        yamlValidation.classList.add("is-skipped");
        return;
      }
      try {
        window.jsyaml.load(editor.value);
        yamlValidation.textContent = "Składnia YAML poprawna";
        yamlValidation.classList.add("is-valid");
      } catch (error) {
        const reason = typeof error?.reason === "string" ? error.reason : "";
        const line = Number(error?.mark?.line);
        const column = Number(error?.mark?.column);
        const location = Number.isFinite(line) && Number.isFinite(column)
          ? ` · linia ${line + 1}, kolumna ${column + 1}`
          : "";
        yamlValidation.textContent = `Błąd YAML${location}: ${yamlErrorLabel(reason)}`;
        yamlValidation.classList.add("is-invalid");
        if (reason !== "") yamlValidation.title = reason;
      }
    };

    const scheduleYamlValidation = () => {
      if (editor.dataset.editorLanguage !== "yaml" || !(yamlValidation instanceof HTMLElement)) return;
      window.clearTimeout(validationTimer);
      yamlValidation.classList.remove("is-valid", "is-invalid", "is-skipped");
      yamlValidation.classList.add("is-checking");
      yamlValidation.textContent = "Sprawdzanie składni…";
      yamlValidation.setAttribute("aria-busy", "true");
      validationTimer = window.setTimeout(validateYaml, 400);
    };

    const sync = () => {
      updateLines();
      updatePosition();
      updateDirty();
      scheduleHighlight();
      scheduleYamlValidation();
    };

    editor.addEventListener("input", sync);
    editor.addEventListener("click", updatePosition);
    editor.addEventListener("keyup", updatePosition);
    editor.addEventListener("scroll", () => {
      if (lines instanceof HTMLElement) lines.scrollTop = editor.scrollTop;
      syncHighlightScroll();
    });
    editor.addEventListener("keydown", (event) => {
      if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === "s") {
        event.preventDefault();
        if (editor.readOnly) return;
        form.requestSubmit();
        return;
      }
      if (editor.readOnly) return;
      if (event.key !== "Tab") return;
      event.preventDefault();
      const start = editor.selectionStart ?? 0;
      const end = editor.selectionEnd ?? start;
      const insert = "    ";
      editor.setRangeText(insert, start, end, "end");
      editor.dispatchEvent(new Event("input", { bubbles: true }));
    });
    form.addEventListener("submit", () => {
      dirty = false;
      if (status instanceof HTMLElement) {
        status.textContent = "Zapisywanie…";
        status.classList.remove("is-dirty");
      }
    });
    window.addEventListener("beforeunload", (event) => {
      if (!dirty) return;
      event.preventDefault();
      event.returnValue = "";
    });

    sync();
  });
})();
