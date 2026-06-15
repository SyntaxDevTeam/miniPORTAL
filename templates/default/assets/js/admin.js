const sidebarElement = document.querySelector("#adminMobileSidebar");
const sidebarToggle = document.querySelector("[data-admin-sidebar-toggle]");

sidebarToggle?.addEventListener("click", () => {
  if (!sidebarElement || !window.bootstrap) {
    return;
  }

  window.bootstrap.Offcanvas.getOrCreateInstance(sidebarElement).show();
});

document.querySelectorAll("[data-admin-nav-target]").forEach((link) => {
  link.addEventListener("click", () => {
    document.querySelectorAll("[data-admin-nav-target]").forEach((item) => {
      item.classList.remove("active");
      item.removeAttribute("aria-current");
    });

    link.classList.add("active");
    link.setAttribute("aria-current", "page");
  });
});

const contentSearch = document.querySelector("[data-content-search]");
const contentStatus = document.querySelector("[data-content-status]");
const contentRows = [...document.querySelectorAll("[data-content-row]")];
const emptyRow = document.querySelector("[data-filter-empty]");

const filterContentRows = () => {
  const search = contentSearch?.value.trim().toLocaleLowerCase("pl-PL") || "";
  const status = contentStatus?.value || "all";
  let visibleRows = 0;

  contentRows.forEach((row) => {
    const matchesSearch = row.textContent.toLocaleLowerCase("pl-PL").includes(search);
    const matchesStatus = status === "all" || row.dataset.status === status;
    const visible = matchesSearch && matchesStatus;

    row.hidden = !visible;
    if (visible) visibleRows += 1;
  });

  if (emptyRow) {
    emptyRow.hidden = visibleRows !== 0;
  }
};

contentSearch?.addEventListener("input", filterContentRows);
contentStatus?.addEventListener("change", filterContentRows);

const demoForm = document.querySelector("[data-admin-demo-form]");
const saveStatus = document.querySelector("[data-save-status]");

demoForm?.addEventListener("submit", (event) => {
  event.preventDefault();

  if (!saveStatus) {
    return;
  }

  saveStatus.className = "alert alert-success mt-3 mb-0";
  saveStatus.textContent = "Prototyp: formularz przeszedł do stanu sukcesu. Backend zostanie podłączony w kolejnym etapie.";
  saveStatus.hidden = false;
});

document.querySelectorAll("[data-provider-demo]").forEach((button) => {
  button.addEventListener("click", () => {
    const provider = button.dataset.providerDemo;
    const loginStatus = document.querySelector("[data-login-status]");

    if (loginStatus) {
      loginStatus.textContent = `Prototyp: wybrano dostawcę ${provider}. Przekierowanie OAuth nie jest jeszcze aktywne.`;
      loginStatus.hidden = false;
    }
  });
});

const escapeHtml = (value) => value
  .replaceAll("&", "&amp;")
  .replaceAll("<", "&lt;")
  .replaceAll(">", "&gt;")
  .replaceAll('"', "&quot;");

const inlineMarkdownToHtml = (value) => escapeHtml(value)
  .replace(
    /\[!\[([^\]]*)]\((https?:\/\/[^ )]+|\/[^ )]+)\)]\((https?:\/\/[^ )]+|\/[^ )]+)\)/g,
    '<a href="$3"><img src="$2" alt="$1" loading="lazy"></a>'
  )
  .replace(
    /!\[([^\]]*)]\((https?:\/\/[^ )]+|\/[^ )]+)\)/g,
    '<img src="$2" alt="$1" loading="lazy">'
  )
  .replace(/`([^`]+)`/g, "<code>$1</code>")
  .replace(/\*\*(.+?)\*\*/g, "<strong>$1</strong>")
  .replace(/__(.+?)__/g, "<strong>$1</strong>")
  .replace(/~~(.+?)~~/g, "<s>$1</s>")
  .replace(/(^|[^*])\*([^*\n]+)\*/g, "$1<em>$2</em>")
  .replace(/\[([^\]]+)]\((https?:\/\/|mailto:|\/|#|index\.php)([^ )]+)\)/g, '<a href="$2$3">$1</a>');

const markdownToHtml = (markdown) => {
  const lines = markdown.replaceAll("\r\n", "\n").split("\n");
  const output = [];
  let paragraph = [];
  let list = null;
  let code = null;

  const flushParagraph = () => {
    if (paragraph.length) output.push(`<p>${inlineMarkdownToHtml(paragraph.join(" "))}</p>`);
    paragraph = [];
  };
  const closeList = () => {
    if (list) output.push(`</${list}>`);
    list = null;
  };

  lines.forEach((line) => {
    if (code !== null) {
      if (/^ {0,3}```/.test(line)) {
        output.push(`<pre><code>${escapeHtml(code.join("\n"))}</code></pre>`);
        code = null;
      } else {
        code.push(line);
      }
      return;
    }
    if (/^ {0,3}```/.test(line)) {
      flushParagraph();
      closeList();
      code = [];
      return;
    }
    const heading = line.match(/^ {0,3}(#{1,6})\s+(.+)$/);
    if (heading) {
      flushParagraph();
      closeList();
      const level = heading[1].length;
      output.push(`<h${level}>${inlineMarkdownToHtml(heading[2])}</h${level}>`);
      return;
    }
    const item = line.match(/^ {0,3}([-+*]|\d+[.)])\s+(.+)$/);
    if (item) {
      flushParagraph();
      const nextList = /^\d/.test(item[1]) ? "ol" : "ul";
      if (list !== nextList) {
        closeList();
        list = nextList;
        output.push(`<${list}>`);
      }
      output.push(`<li>${inlineMarkdownToHtml(item[2].replace(/^\[[ xX]]\s+/, ""))}</li>`);
      return;
    }
    if (/^ {0,3}>/.test(line)) {
      flushParagraph();
      closeList();
      output.push(`<blockquote><p>${inlineMarkdownToHtml(line.replace(/^ {0,3}>\s?/, ""))}</p></blockquote>`);
      return;
    }
    if (line.trim() === "") {
      flushParagraph();
      closeList();
      return;
    }
    paragraph.push(line);
  });
  flushParagraph();
  closeList();
  if (code !== null) output.push(`<pre><code>${escapeHtml(code.join("\n"))}</code></pre>`);

  return output.join("");
};

const htmlToMarkdown = (html) => {
  const root = document.createElement("div");
  root.innerHTML = html;
  const walk = (node, depth = 0) => {
    if (node.nodeType === Node.TEXT_NODE) return node.textContent || "";
    if (node.nodeType !== Node.ELEMENT_NODE) return "";
    const content = [...node.childNodes].map((child) => walk(child, depth + 1)).join("");
    switch (node.tagName.toLowerCase()) {
      case "h1": return `# ${content}\n\n`;
      case "h2": return `## ${content}\n\n`;
      case "h3": return `### ${content}\n\n`;
      case "h4": return `#### ${content}\n\n`;
      case "h5": return `##### ${content}\n\n`;
      case "h6": return `###### ${content}\n\n`;
      case "p": return `${content.trim()}\n\n`;
      case "br": return "  \n";
      case "strong":
      case "b": return `**${content}**`;
      case "em":
      case "i": return `*${content}*`;
      case "s":
      case "strike": return `~~${content}~~`;
      case "code": return node.parentElement?.tagName.toLowerCase() === "pre" ? content : `\`${content}\``;
      case "pre": return `\`\`\`\n${node.textContent || ""}\n\`\`\`\n\n`;
      case "blockquote": return `${content.trim().split("\n").map((line) => `> ${line}`).join("\n")}\n\n`;
      case "a": return `[${content}](${node.getAttribute("href") || "#"})`;
      case "img": return `![${node.getAttribute("alt") || ""}](${node.getAttribute("src") || ""})`;
      case "li": return `${node.parentElement?.tagName === "OL" ? "1." : "-"} ${content.trim()}\n`;
      case "ul":
      case "ol": return `${content}\n`;
      case "hr": return "---\n\n";
      default: return content;
    }
  };

  return walk(root).replace(/\n{3,}/g, "\n\n").trim();
};

document.querySelectorAll("[data-richtext]").forEach((editor) => {
  const surface = editor.querySelector("[data-richtext-surface]");
  const markdown = editor.querySelector("[data-richtext-markdown]");
  const input = editor.querySelector("[data-richtext-input]");
  const formatInput = editor.querySelector("[data-richtext-format-input]");
  const toolbar = editor.querySelector("[data-richtext-toolbar]");
  const hint = editor.querySelector("[data-richtext-hint]");

  if (!surface || !markdown || !input || !formatInput || !toolbar) {
    return;
  }

  const sync = () => {
    input.value = formatInput.value === "markdown" ? markdown.value : surface.innerHTML;
  };
  const setMode = (mode, convert = true) => {
    const nextMode = mode === "markdown" ? "markdown" : "html";
    if (convert && formatInput.value !== nextMode) {
      if (nextMode === "markdown") {
        markdown.value = htmlToMarkdown(surface.innerHTML);
      } else {
        surface.innerHTML = markdownToHtml(markdown.value);
      }
    }
    formatInput.value = nextMode;
    surface.hidden = nextMode !== "html";
    toolbar.hidden = nextMode !== "html";
    markdown.hidden = nextMode !== "markdown";
    editor.querySelectorAll("[data-richtext-mode]").forEach((button) => {
      button.classList.toggle("is-active", button.dataset.richtextMode === nextMode);
      button.setAttribute("aria-pressed", button.dataset.richtextMode === nextMode ? "true" : "false");
    });
    if (hint) {
      hint.textContent = nextMode === "markdown"
        ? "Markdown w stylu GitHub: tabele, listy zadań, kod, linki i obrazy."
        : "Tryb wizualny zapisuje kontrolowany HTML.";
    }
    sync();
  };

  editor.querySelectorAll("[data-richtext-mode]").forEach((button) => {
    button.addEventListener("click", () => setMode(button.dataset.richtextMode));
  });
  formatInput.addEventListener("change", () => setMode(formatInput.value));
  editor.querySelectorAll("[data-richtext-command]").forEach((button) => {
    button.addEventListener("click", () => {
      surface.focus();
      document.execCommand(button.dataset.richtextCommand, false);
      sync();
    });
  });

  editor.querySelectorAll("[data-richtext-block]").forEach((button) => {
    button.addEventListener("click", () => {
      surface.focus();
      document.execCommand("formatBlock", false, button.dataset.richtextBlock);
      sync();
    });
  });

  surface.addEventListener("input", sync);
  markdown.addEventListener("input", sync);
  surface.addEventListener("paste", (event) => {
    event.preventDefault();
    const text = event.clipboardData?.getData("text/plain") || "";
    document.execCommand("insertText", false, text);
  });
  surface.closest("form")?.addEventListener("submit", sync);
  editor.refreshRichtext = () => {
    if (formatInput.value === "markdown") {
      markdown.value = input.value;
    } else {
      surface.innerHTML = input.value;
    }
    setMode(formatInput.value, false);
  };
  setMode(formatInput.value, false);
});

const autosaveClearKey = new URLSearchParams(window.location.search).get("autosave_clear");
if (autosaveClearKey) {
  localStorage.removeItem(`miniportal:draft:v2:${autosaveClearKey}`);
  localStorage.removeItem(`miniportal:${autosaveClearKey}`);
  window.history.replaceState(
    {},
    "",
    window.location.href.replace(/([&?])autosave_clear=[^&]*&?/, "$1").replace(/[?&]$/, "")
  );
}

document.querySelectorAll('form').forEach((form) => {
  if (form.dataset.confirm) {
    form.addEventListener("submit", (event) => {
      if (!window.confirm(form.dataset.confirm)) {
        event.preventDefault();
      }
    });
  }

  const keyInput = form.querySelector('input[name="_autosave_key"]');
  if (!keyInput?.value) {
    return;
  }

  const storageKey = `miniportal:draft:v2:${keyInput.value}`;
  const legacyStorageKey = `miniportal:${keyInput.value}`;
  localStorage.removeItem(legacyStorageKey);
  const fields = [...form.elements].filter((field) =>
    field.name && !["_token", "_autosave_key", "id"].includes(field.name)
  );
  let timer = null;
  const status = document.createElement("div");
  status.className = "form-text mt-3";
  status.setAttribute("role", "status");
  form.append(status);

  const currentValues = () => {
    const values = {};
    fields.forEach((field) => {
      values[field.name] = field.type === "checkbox" ? field.checked : field.value;
    });
    return values;
  };
  const applyValues = (values) => {
    fields.forEach((field) => {
      if (!(field.name in values)) return;
      if (field.type === "checkbox") {
        field.checked = Boolean(values[field.name]);
      } else {
        field.value = values[field.name];
      }
    });
    form.querySelectorAll("[data-richtext]").forEach((editor) => editor.refreshRichtext?.());
  };

  try {
    const saved = JSON.parse(localStorage.getItem(storageKey) || "null");
    if (saved?.values && JSON.stringify(saved.values) !== JSON.stringify(currentValues())) {
      const recovery = document.createElement("div");
      recovery.className = "autosave-recovery";
      recovery.innerHTML = "<span>Znaleziono lokalną wersję roboczą. Dane z bazy pozostają w formularzu.</span>";

      const actions = document.createElement("span");
      actions.className = "autosave-recovery-actions";
      const restore = document.createElement("button");
      restore.className = "btn btn-sm btn-outline-light";
      restore.type = "button";
      restore.textContent = "Przywróć szkic";
      restore.addEventListener("click", () => {
        applyValues(saved.values);
        recovery.remove();
        status.textContent = "Przywrócono lokalną wersję roboczą.";
      });

      const discard = document.createElement("button");
      discard.className = "btn btn-sm btn-outline-danger";
      discard.type = "button";
      discard.textContent = "Odrzuć szkic";
      discard.addEventListener("click", () => {
        localStorage.removeItem(storageKey);
        recovery.remove();
        status.textContent = "Lokalna wersja robocza została usunięta.";
      });

      actions.append(restore, discard);
      recovery.append(actions);
      form.prepend(recovery);
    }
  } catch {
    localStorage.removeItem(storageKey);
  }

  const saveDraft = () => {
    localStorage.setItem(storageKey, JSON.stringify({ savedAt: Date.now(), values: currentValues() }));
    status.textContent = "Wersja robocza zapisana lokalnie.";
  };

  form.addEventListener("input", () => {
    clearTimeout(timer);
    timer = setTimeout(saveDraft, 700);
  });
  form.addEventListener("change", () => {
    clearTimeout(timer);
    timer = setTimeout(saveDraft, 200);
  });
  form.addEventListener("submit", () => clearTimeout(timer));
});
