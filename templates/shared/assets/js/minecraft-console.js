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

    const poll = async () => {
      try {
        const url = new URL(root.dataset.outputUrl || "", window.location.href);
        url.searchParams.set("offset", String(consoleOffset));
        const response = await fetch(url.toString(), {
          cache: "no-store",
          headers: { Accept: "application/json" },
        });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        const data = await response.json();
        if (data.source === "log") {
          const nextChunk = typeof data.output === "string" ? data.output : "";
          const shouldReset = Boolean(data.reset) || !streamInitialized;
          applyConsoleOutput(shouldReset ? nextChunk : rawOutput + nextChunk, shouldReset);
          consoleOffset = Number.isFinite(Number(data.offset)) ? Number(data.offset) : consoleOffset;
          streamInitialized = true;
        } else {
          streamInitialized = false;
          consoleOffset = 0;
          applyConsoleOutput(typeof data.output === "string" ? data.output : "");
        }
      } catch {
        showPlainMessage("Nie udało się pobrać bufora konsoli.");
        consoleOffset = 0;
        streamInitialized = false;
      } finally {
        pollTimer = window.setTimeout(poll, 1200);
      }
    };

    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      const body = new FormData(form);
      try {
        const response = await fetch(root.dataset.commandUrl || "", { method: "POST", body });
        const command = form.elements.namedItem("command");
        if (response.ok && command instanceof HTMLInputElement) command.value = "";
        window.clearTimeout(pollTimer);
        poll();
      } catch {
        showPlainMessage("Nie udało się wysłać komendy do konsoli.");
      }
    });

    poll();
  });
})();
