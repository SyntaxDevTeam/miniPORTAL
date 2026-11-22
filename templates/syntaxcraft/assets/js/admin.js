const sidebarElement = document.querySelector("#adminMobileSidebar");
const sidebarToggle = document.querySelector("[data-admin-sidebar-toggle]");

sidebarToggle?.addEventListener("click", () => {
  if (!sidebarElement || !window.bootstrap) {
    return;
  }

  window.bootstrap.Offcanvas.getOrCreateInstance(sidebarElement).show();
});

document.querySelectorAll("[data-admin-mobile-nav-link]").forEach((link) => {
  link.addEventListener("click", () => {
    if (!sidebarElement || !window.bootstrap) {
      return;
    }

    window.bootstrap.Offcanvas.getOrCreateInstance(sidebarElement).hide();
  });
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

const adminSearch = document.querySelector("[data-admin-search]");
const adminSearchInput = adminSearch?.querySelector("[data-admin-search-input]");
const adminSearchResults = adminSearch?.querySelector("[data-admin-search-results]");
const adminSearchItems = [...(adminSearch?.querySelectorAll("[data-admin-search-item]") || [])];
const adminSearchEmpty = adminSearch?.querySelector("[data-admin-search-empty]");
let adminSearchActive = -1;

const closeAdminSearch = () => {
  if (!adminSearchInput || !adminSearchResults) return;
  adminSearchResults.hidden = true;
  adminSearchInput.setAttribute("aria-expanded", "false");
  adminSearchActive = -1;
  adminSearchItems.forEach((item) => item.classList.remove("is-active"));
};

const filterAdminSearch = () => {
  if (!adminSearchInput || !adminSearchResults) return;
  const query = adminSearchInput.value.trim().toLocaleLowerCase("pl-PL");
  let matches = 0;
  adminSearchItems.forEach((item) => {
    const visible = query.length >= 2 && matches < 8 && (item.dataset.search || "").includes(query);
    item.hidden = !visible;
    if (visible) matches += 1;
    item.classList.remove("is-active");
  });
  if (adminSearchEmpty) adminSearchEmpty.hidden = query.length < 2 || matches !== 0;
  adminSearchResults.hidden = query.length < 2;
  adminSearchInput.setAttribute("aria-expanded", query.length >= 2 ? "true" : "false");
  adminSearchActive = -1;
};

adminSearchInput?.addEventListener("input", filterAdminSearch);
adminSearchInput?.addEventListener("keydown", (event) => {
  const visible = adminSearchItems.filter((item) => !item.hidden);
  if (event.key === "Escape") {
    closeAdminSearch();
    return;
  }
  if (!["ArrowDown", "ArrowUp", "Enter"].includes(event.key) || visible.length === 0) return;
  event.preventDefault();
  if (event.key === "Enter" && adminSearchActive >= 0) {
    visible[adminSearchActive].click();
    return;
  }
  const step = event.key === "ArrowUp" ? -1 : 1;
  adminSearchActive = (adminSearchActive + step + visible.length) % visible.length;
  visible.forEach((item, index) => item.classList.toggle("is-active", index === adminSearchActive));
  visible[adminSearchActive].scrollIntoView({ block: "nearest" });
});
document.addEventListener("click", (event) => {
  if (adminSearch && !adminSearch.contains(event.target)) closeAdminSearch();
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

document.querySelectorAll("[data-checkbox-group]").forEach((group) => {
  const checkboxes = [...group.querySelectorAll('input[type="checkbox"]')];
  const count = group.querySelector("[data-checkbox-group-count]");

  const refresh = () => {
    const selected = checkboxes.filter((checkbox) => checkbox.checked).length;
    if (count) {
      count.textContent = `${selected}/${checkboxes.length}`;
    }
  };

  group.querySelectorAll("[data-checkbox-group-set]").forEach((button) => {
    button.addEventListener("click", () => {
      const checked = button.dataset.checkboxGroupSet === "all";
      checkboxes.forEach((checkbox) => {
        checkbox.checked = checked;
      });
      refresh();
    });
  });
  checkboxes.forEach((checkbox) => checkbox.addEventListener("change", refresh));
  refresh();
});

const escapeHtml = (value) => value
  .replaceAll("&", "&amp;")
  .replaceAll("<", "&lt;")
  .replaceAll(">", "&gt;")
  .replaceAll('"', "&quot;");

const markdownHtmlTag = (tag) => {
  const match = tag.match(/^<(\/?)([a-z][a-z0-9]*)\b/i);
  const name = match?.[2].toLowerCase();
  const allowed = ["p", "br", "strong", "b", "em", "i", "u", "s", "del", "ul", "ol", "li", "blockquote", "h1", "h2", "h3", "h4", "h5", "h6", "table", "thead", "tbody", "tfoot", "tr", "th", "td", "div", "span", "hr", "pre", "code", "a", "img", "details", "summary", "sub", "sup", "kbd", "mark"];
  if (!allowed.includes(name)) return "";
  if (match[1]) return ["img", "br", "hr"].includes(name) ? "" : `</${name}>`;
  if (name === "code") {
    const node = new DOMParser().parseFromString(tag + "</code>", "text/html").querySelector("code");
    const language = node?.getAttribute("class") || "";
    return /^language-[a-zA-Z0-9_-]{1,40}$/.test(language) ? `<code class="${language}">` : "<code>";
  }
  if (!["a", "img"].includes(name)) {
    let alignment = "";
    if (["div", "p", "h1", "h2", "h3", "h4", "h5", "h6", "blockquote", "th", "td"].includes(name)) {
      // A neutral element also parses attributes of table cells outside a table.
      const attributes = tag.replace(/^<[a-z][a-z0-9]*/i, "<div");
      const node = new DOMParser().parseFromString(attributes, "text/html").querySelector("div");
      const align = (node?.getAttribute("align") || "").trim().toLowerCase();
      if (["left", "center", "right", "justify"].includes(align)) alignment = ` align="${align}"`;
    }
    return `<${name}${alignment}>`;
  }
  const node = new DOMParser().parseFromString(tag, "text/html").querySelector(name);
  const url = (node?.getAttribute(name === "a" ? "href" : "src") || "").trim();
  const safe = url && !/[\x00-\x20\x7F\\]/.test(url) && !url.startsWith("//")
    && (/^(https?:\/\/|\/)/i.test(url) || (name === "a" && /^(mailto:|#|\.\.?\/|[a-z0-9][a-z0-9._~/?#%&=+\-]*$)/i.test(url)));
  if (name === "a") {
    const anchor = (node?.getAttribute("id") ?? node?.getAttribute("name") ?? "").trim();
    const anchorAttribute = /^[a-z][a-z0-9_-]{0,99}$/i.test(anchor) ? ` id="${anchor}"` : "";
    return `<a${anchorAttribute}${safe ? ` href="${escapeHtml(url)}" rel="nofollow noopener noreferrer"` : ""}>`;
  }
  if (!safe) return "";
  const width = richtextImageWidthFromElement(node);
  const attrs = [...node.classList].filter(value => /^content-image-(left|right|center|wide|original|small|medium|large|custom)$/.test(value)).map(value => `.${value}`).join(" ");
  return `<img src="${escapeHtml(url)}" alt="${escapeHtml(node.getAttribute("alt") || "")}" class="${richtextImageClasses(`${attrs} ${width ? ".content-image-custom" : ""}`)}"${richtextImageStyle(width)} loading="lazy">`;
};

const inlineMarkdownToHtml = (value) => {
  const tokens = [];
  const token = (html) => { tokens.push(html); return `\x1A${tokens.length - 1}\x1A`; };
  const hasHtml = /<\/?[a-z][a-z0-9]*\b/i.test(value);
  value = value.replaceAll("\x1A", "")
    .replace(/`[^`\n]+`|<(pre|code)\b[^>]*>[\s\S]*?<\/\1\s*>/gi, html => html.startsWith("`") ? html : token(sanitizeMarkdownPreview(html)))
    .replace(/\\([\\`*{}\[\]()#+\-.!_>~|])/g, (_match, literal) => token(escapeHtml(literal)))
    .replace(/`([^`\n]+)`/g, (_match, code) => token(`<code>${escapeHtml(code)}</code>`))
    .replace(/<(script|style|iframe|object|embed|svg|math)\b[^>]*>[\s\S]*?<\/\1\s*>/gi, "")
    .replace(/<\/?[a-z][a-z0-9]*(?:\s+(?:[^"'<>]|"[^"]*"|'[^']*')*)?\s*\/?>/gi, tag => token(markdownHtmlTag(tag)))
    .replace(/&(?:#[0-9]+|#x[a-f0-9]+|[a-z][a-z0-9]+);/gi, entity => hasHtml ? token(entity) : entity);
  const html = escapeHtml(value)
  .replace(
    /\[!\[([^\]]*)]\((https?:\/\/[^ )]+|\/[^ )]+)\)]\((https?:\/\/[^ )]+|\/[^ )]+)\)/g,
    '<a href="$3"><img class="content-image content-image-original" src="$2" alt="$1" loading="lazy"></a>'
  )
  .replace(
    /!\[([^\]]*)]\((https?:\/\/[^ )]+|\/[^ )]+)\)\{([^}]*)}/g,
    (_match, alt, src, attrs) => `<img src="${src}" alt="${alt}" class="${richtextImageClasses(attrs)}"${richtextImageStyle(richtextImageWidth(attrs))} loading="lazy">`
  )
  .replace(
    /!\[([^\]]*)]\((https?:\/\/[^ )]+|\/[^ )]+)\)/g,
    '<img src="$2" alt="$1" class="content-image content-image-original" loading="lazy">'
  )
  .replace(/\*\*(.+?)\*\*/g, "<strong>$1</strong>")
  .replace(/__(.+?)__/g, "<strong>$1</strong>")
  .replace(/~~(.+?)~~/g, "<s>$1</s>")
  .replace(/(^|[^*])\*([^*\n]+)\*/g, "$1<em>$2</em>")
  .replace(/\[([^\]]+)]\((https?:\/\/|mailto:|\/|#|index\.php)([^ )]+)\)/g, '<a href="$2$3">$1</a>');

  const restored = html.replace(/\x1A(\d+)\x1A/g, (_match, index) => tokens[Number(index)] || "");
  return restored;
};

const sanitizeMarkdownPreview = (html) => {
  // Sanitize the assembled document so HTML containers can span Markdown paragraphs.
  const doc = new DOMParser().parseFromString(html, "text/html");
  const walk = (node) => {
    if (node.nodeType === Node.TEXT_NODE) return escapeHtml(node.textContent);
    if (node.nodeType !== Node.ELEMENT_NODE) return "";
    if (["SCRIPT", "STYLE", "IFRAME", "OBJECT", "EMBED", "SVG", "MATH"].includes(node.tagName)) return "";
    const opening = markdownHtmlTag(node.outerHTML.match(/^<(?:[^"'>]|"[^"]*"|'[^']*')*>/)?.[0] || "");
    const content = [...node.childNodes].map(walk).join("");
    return opening + content + (opening ? markdownHtmlTag(`</${node.tagName}>`) : "");
  };
  return [...doc.body.childNodes].map(walk).join("");
};

const markdownToHtml = (markdown) => {
  const lines = markdown.replaceAll("\r\n", "\n").split("\n");
  const output = [];
  let paragraph = [];
  let list = null;
  let code = null;
  let codeLanguage = "";

  const flushParagraph = () => {
    if (paragraph.length) {
      const text = paragraph
        .map((line) => line.replace(/ {2}$/, ""))
        .map(inlineMarkdownToHtml)
        .join("<br>");
      output.push(`<p>${text}</p>`);
    }
    paragraph = [];
  };
  const closeList = () => {
    if (list) output.push(`</${list}>`);
    list = null;
  };
  const tableCells = (line) => line
    .trim()
    .replace(/^\|/, "")
    .replace(/\|$/, "")
    .split("|")
    .map((cell) => cell.trim());
  const isTableSeparator = (line = "") => {
    const cells = tableCells(line);
    return cells.length > 0 && cells.every((cell) => /^:?-{3,}:?$/.test(cell));
  };
  const isTableHeader = (index) => (lines[index] || "").includes("|") && isTableSeparator(lines[index + 1] || "");

  for (let index = 0; index < lines.length; index += 1) {
    const line = lines[index];
    if (code !== null) {
      if (/^ {0,3}```/.test(line)) {
        output.push(`<pre><code${codeLanguage ? ` class="language-${codeLanguage}"` : ""}>${escapeHtml(code.join("\n"))}</code></pre>`);
        code = null;
      } else {
        code.push(line);
      }
      continue;
    }
    if (/^ {0,3}```/.test(line)) {
      flushParagraph();
      closeList();
      codeLanguage = line.match(/^ {0,3}```([a-zA-Z0-9_-]*)\s*$/)?.[1] || "";
      code = [];
      continue;
    }
    if (/^ {0,3}<\/?(?:p|div|h[1-6]|ul|ol|li|blockquote|table|thead|tbody|tfoot|tr|th|td|pre|hr|details|summary|script|style|iframe|object|embed|svg|math)\b/i.test(line)) {
      flushParagraph();
      closeList();
      const raw = [line];
      while (index + 1 < lines.length && lines[index + 1].trim() !== "") raw.push(lines[++index]);
      output.push(inlineMarkdownToHtml(raw.join("\n")));
      continue;
    }
    const heading = line.match(/^ {0,3}(#{1,6})\s+(.+)$/);
    if (heading) {
      flushParagraph();
      closeList();
      const level = heading[1].length;
      output.push(`<h${level}>${inlineMarkdownToHtml(heading[2])}</h${level}>`);
      continue;
    }
    if (isTableHeader(index)) {
      flushParagraph();
      closeList();
      const headers = tableCells(line);
      index += 1;
      const rows = [];
      while (index + 1 < lines.length && lines[index + 1].includes("|") && lines[index + 1].trim() !== "") {
        index += 1;
        rows.push(tableCells(lines[index]));
      }
      const headerHtml = headers.map((cell) => `<th>${inlineMarkdownToHtml(cell)}</th>`).join("");
      const bodyHtml = rows.map((row) => {
        const cells = headers.map((_header, column) => `<td>${inlineMarkdownToHtml(row[column] || "")}</td>`).join("");
        return `<tr>${cells}</tr>`;
      }).join("");
      output.push(`<table><thead><tr>${headerHtml}</tr></thead><tbody>${bodyHtml}</tbody></table>`);
      continue;
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
      continue;
    }
    if (/^ {0,3}>/.test(line)) {
      flushParagraph();
      closeList();
      output.push(`<blockquote><p>${inlineMarkdownToHtml(line.replace(/^ {0,3}>\s?/, ""))}</p></blockquote>`);
      continue;
    }
    if (line.trim() === "") {
      flushParagraph();
      closeList();
      continue;
    }
    paragraph.push(line);
  }
  flushParagraph();
  closeList();
  if (code !== null) output.push(`<pre><code${codeLanguage ? ` class="language-${codeLanguage}"` : ""}>${escapeHtml(code.join("\n"))}</code></pre>`);

  return sanitizeMarkdownPreview(output.join(""));
};

// HTML is valid in mixed Markdown. Keep its structure instead of a lossy conversion.
const htmlToMarkdown = (html) => {
  const root = document.createElement("div");
  root.innerHTML = sanitizeMarkdownPreview(html);
  const walk = (node) => {
    if (node.nodeType === Node.TEXT_NODE) {
      node.textContent = node.textContent.replace(/[\\`*_~[\]!]/g, "\\$&");
    } else if (!["PRE", "CODE"].includes(node.tagName)) {
      [...node.childNodes].forEach(walk);
    }
  };
  walk(root);
  return root.innerHTML;
};

const richtextImageClasses = (attributes = "") => {
  const allowed = new Set([
    "content-image-left",
    "content-image-right",
    "content-image-center",
    "content-image-wide",
    "content-image-original",
    "content-image-small",
    "content-image-medium",
    "content-image-large",
    "content-image-custom",
  ]);
  const classes = ["content-image"];
  attributes.split(/\s+/).forEach((attribute) => {
    const className = attribute.replace(/^\./, "");
    if (allowed.has(className)) classes.push(className);
  });
  if (classes.length === 1) classes.push("content-image-original");

  return [...new Set(classes)].join(" ");
};

const richtextNormalizeImageWidth = (value = "") => {
  const match = String(value).trim().toLowerCase().replace(/\s+/g, "").match(/^([0-9]+(?:\.[0-9]+)?)(px|rem|em|%|vw)$/);
  if (!match) return null;
  const number = Number.parseFloat(match[1]);
  const unit = match[2];
  const limits = {
    px: [1, 2400],
    rem: [0.1, 160],
    em: [0.1, 160],
    "%": [1, 100],
    vw: [1, 100],
  };
  const [min, max] = limits[unit];
  if (number < min || number > max) return null;
  const formatted = Number.isInteger(number) ? String(number) : String(number).replace(/0+$/, "").replace(/\.$/, "");

  return `${formatted}${unit}`;
};

const richtextImageWidth = (attributes = "") => {
  const match = attributes.match(/(?:^|\s)width=([0-9]+(?:\.[0-9]+)?(?:px|rem|em|%|vw))(?:\s|$)/i);
  return match ? richtextNormalizeImageWidth(match[1]) : null;
};

const richtextImageStyle = (width) => width ? ` style="--content-image-width:${width};"` : "";

const richtextImageWidthFromElement = (image) => {
  const custom = richtextNormalizeImageWidth(image.style.getPropertyValue("--content-image-width"));
  const width = image.getAttribute("width") || "";
  return custom || (/^[0-9]{1,4}$/.test(width) ? richtextNormalizeImageWidth(`${Number(width)}px`) : null);
};

const richtextImageMarkdown = (url, alt, size, align, width) => {
  const classes = [
    size === "custom" ? "custom" : size,
    align === "none" ? "" : align,
  ]
    .filter(Boolean)
    .map((value) => `.content-image-${value}`)
    .join(" ");
  const attrs = [classes, size === "custom" ? `width=${width}` : ""].filter(Boolean).join(" ");

  return `![${alt}](${url})${attrs ? `{${attrs}}` : ""}`;
};

document.querySelectorAll("[data-richtext]").forEach((editor) => {
  const surface = editor.querySelector("[data-richtext-surface]");
  const markdown = editor.querySelector("[data-richtext-markdown]");
  const input = editor.querySelector("[data-richtext-input]");
  const formatInput = editor.querySelector("[data-richtext-format-input]");
  const toolbar = editor.querySelector("[data-richtext-toolbar]");
  const hint = editor.querySelector("[data-richtext-hint]");
  const imageOptions = editor.querySelector("[data-richtext-image-options]");
  const imageSize = editor.querySelector("[data-richtext-image-size]");
  const imageAlign = editor.querySelector("[data-richtext-image-align]");
  const imageWidth = editor.querySelector("[data-richtext-image-width]");
  const imageFile = editor.querySelector("[data-richtext-image-file]");

  if (!surface || !markdown || !input || !formatInput || !toolbar) {
    return;
  }

  let activeMode = formatInput.value;
  const source = document.createElement("textarea");
  source.className = "richtext-markdown form-control";
  source.rows = 18;
  source.hidden = true;
  source.setAttribute("aria-label", "Kod HTML treści");
  source.spellcheck = false;
  markdown.after(source);
  // Keep the authored document separate from the rendered visual view.
  let documentFormat = formatInput.value === "markdown" ? "markdown" : "html";
  let content = documentFormat === "markdown" ? markdown.value : input.value;
  let visualSnapshot = sanitizeMarkdownPreview(surface.innerHTML);
  const sync = () => {
    if (activeMode === "source") content = source.value;
    else if (activeMode === "markdown") content = markdown.value;
    else {
      const visualContent = sanitizeMarkdownPreview(surface.innerHTML);
      if (visualContent !== visualSnapshot) {
        // Ignore selection markers; only a content edit changes the stored format.
        content = visualContent;
        documentFormat = "html";
        visualSnapshot = visualContent;
      }
    }
    input.value = content;
    formatInput.value = documentFormat;
  };
  source.addEventListener("input", sync);
  const setMode = (mode, convert = true) => {
    const nextMode = ["markdown", "source"].includes(mode) ? mode : "html";
    if (convert) sync();
    if (selectedImage) selectImage(null);
    if (nextMode === "markdown" && documentFormat === "html") {
      content = htmlToMarkdown(content);
      documentFormat = "markdown";
    }
    if (nextMode === "html") {
      surface.innerHTML = documentFormat === "markdown"
        ? markdownToHtml(content) : sanitizeMarkdownPreview(content);
      visualSnapshot = sanitizeMarkdownPreview(surface.innerHTML);
    }
    source.value = content;
    markdown.value = content;
    activeMode = nextMode;
    source.setAttribute("aria-label", documentFormat === "markdown" ? "Źródło Markdown i HTML" : "Kod HTML treści");
    const sourceButton = editor.querySelector('[data-richtext-mode="source"]');
    if (sourceButton) sourceButton.textContent = documentFormat === "markdown" ? "Źródło Markdown / HTML" : "Kod HTML";
    surface.hidden = nextMode !== "html";
    toolbar.hidden = nextMode !== "html";
    if (imageOptions) imageOptions.hidden = nextMode === "source";
    markdown.hidden = nextMode !== "markdown";
    source.hidden = nextMode !== "source";
    editor.querySelectorAll("[data-richtext-mode]").forEach((button) => {
      button.classList.toggle("is-active", button.dataset.richtextMode === nextMode);
      button.setAttribute("aria-pressed", button.dataset.richtextMode === nextMode ? "true" : "false");
    });
    if (hint) {
      hint.textContent = nextMode === "markdown"
        ? "Markdown + bezpieczny HTML: tabele, listy, kod, linki i obrazy. Kod dosłowny umieść w backtickach."
        : nextMode === "source"
          ? "Edytujesz źródło w wybranym formacie zapisu. Przełączanie widoków zachowuje treść."
          : "Tryb wizualny zapisuje kontrolowany HTML.";
    }
    sync();
  };

  editor.querySelectorAll("[data-richtext-mode]").forEach((button) => {
    button.addEventListener("click", () => setMode(button.dataset.richtextMode));
  });
  editor.querySelector("[data-richtext-python]")?.addEventListener("click", () => {
    const example = 'import math\n\nkat_stopnie = 30\nsinus = math.sin(math.radians(kat_stopnie))\nprint(f"{sinus:.4f}")';
    if (activeMode === "html") {
      surface.focus();
      const html = `<pre><code class="language-python-run">${escapeHtml(example)}</code></pre><p><br></p>`;
      const selection = window.getSelection();
      if (!selection?.anchorNode || !surface.contains(selection.anchorNode)) {
        const range = document.createRange();
        range.selectNodeContents(surface);
        range.collapse(false);
        selection?.removeAllRanges();
        selection?.addRange(range);
      }
      document.execCommand("insertHTML", false, html);
    } else {
      const area = activeMode === "source" ? source : markdown;
      const text = documentFormat === "markdown" ? `\n\n\`\`\`python-run\n${example}\n\`\`\`\n\n`
        : `\n<pre><code class="language-python-run">${escapeHtml(example)}</code></pre>\n`;
      area.setRangeText(text, area.selectionStart, area.selectionEnd, "end");
      area.focus();
    }
    sync();
    input.dispatchEvent(new Event("input", { bubbles: true }));
  });
  editor.querySelector("[data-richtext-kotlin]")?.addEventListener("click", () => {
    const example = 'fun main() {\n    println("Witaj, Kotlin!")\n}';
    if (activeMode === "html") {
      surface.focus();
      const html = `<pre><code class="language-kotlin-run">${escapeHtml(example)}</code></pre><p><br></p>`;
      const selection = window.getSelection();
      if (!selection?.anchorNode || !surface.contains(selection.anchorNode)) {
        const range = document.createRange();
        range.selectNodeContents(surface);
        range.collapse(false);
        selection?.removeAllRanges();
        selection?.addRange(range);
      }
      document.execCommand("insertHTML", false, html);
    } else {
      const area = activeMode === "source" ? source : markdown;
      const text = documentFormat === "markdown" ? `\n\n\`\`\`kotlin-run\n${example}\n\`\`\`\n\n`
        : `\n<pre><code class="language-kotlin-run">${escapeHtml(example)}</code></pre>\n`;
      area.setRangeText(text, area.selectionStart, area.selectionEnd, "end");
      area.focus();
    }
    sync();
    input.dispatchEvent(new Event("input", { bubbles: true }));
  });
  formatInput.addEventListener("change", () => {
    const requestedFormat = formatInput.value;
    sync();
    if (requestedFormat !== documentFormat) {
      content = requestedFormat === "html" ? markdownToHtml(content) : htmlToMarkdown(content);
      documentFormat = requestedFormat;
    }
    setMode(requestedFormat === "markdown" ? "markdown" : "source", false);
  });
  editor.querySelectorAll("[data-richtext-command]").forEach((button) => {
    button.addEventListener("mousedown", (event) => event.preventDefault());
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

  editor.querySelector("[data-richtext-explanation]")?.addEventListener("mousedown", (event) => {
    event.preventDefault();
  });
  editor.querySelector("[data-richtext-explanation]")?.addEventListener("click", () => {
    const selection = window.getSelection();
    if (!selection || selection.rangeCount === 0) return;

    const range = selection.getRangeAt(0).cloneRange();
    const commonNode = range.commonAncestorContainer.nodeType === Node.ELEMENT_NODE
      ? range.commonAncestorContainer
      : range.commonAncestorContainer.parentElement;
    if (!commonNode || !surface.contains(commonNode)) {
      window.alert("Najpierw zaznacz tekst w edytorze.");
      return;
    }

    const existing = (selection.anchorNode?.nodeType === Node.ELEMENT_NODE
      ? selection.anchorNode
      : selection.anchorNode?.parentElement)?.closest?.("abbr.content-explanation");
    const selectedText = range.toString();
    if (range.collapsed && !(existing instanceof HTMLElement && surface.contains(existing))) {
      window.alert("Najpierw zaznacz tekst, który ma otrzymać wyjaśnienie.");
      return;
    }
    if (!selectedText.trim() && !existing) return;
    if (!existing && range.cloneContents().querySelector("p,h1,h2,h3,h4,h5,h6,blockquote,ul,ol,li,table,thead,tbody,tr,th,td")) {
      window.alert("Wyjaśnienie można dodać do tekstu w obrębie jednego akapitu lub nagłówka.");
      return;
    }

    const currentExplanation = existing?.dataset.explanation || "";
    const answer = window.prompt(
      existing
        ? "Treść wyjaśnienia. Pozostaw puste, aby usunąć oznaczenie:"
        : "Treść wyjaśnienia widoczna po najechaniu lub ustawieniu fokusu:",
      currentExplanation
    );
    if (answer === null) return;
    const explanation = Array.from(answer.replace(/\s+/g, " ").trim()).slice(0, 500).join("");
    if (!explanation) {
      if (existing instanceof HTMLElement && surface.contains(existing)) {
        existing.replaceWith(document.createTextNode(existing.textContent || ""));
        surface.focus();
        sync();
        return;
      }
      window.alert("Wyjaśnienie nie może być puste.");
      return;
    }

    if (existing instanceof HTMLElement && surface.contains(existing)) {
      existing.dataset.explanation = explanation;
      existing.setAttribute("aria-label", `${existing.textContent || ""} — ${explanation}`);
    } else {
      const marker = document.createElement("abbr");
      marker.className = "content-explanation";
      marker.dataset.explanation = explanation;
      marker.setAttribute("aria-label", `${selectedText.trim()} — ${explanation}`);
      marker.tabIndex = 0;
      marker.textContent = selectedText;
      range.deleteContents();
      range.insertNode(marker);
      range.setStartAfter(marker);
      range.collapse(true);
      selection.removeAllRanges();
      selection.addRange(range);
    }
    surface.focus();
    sync();
  });

  let selectedImage = null;
  const resizeHandle = document.createElement("button");
  resizeHandle.type = "button";
  resizeHandle.className = "richtext-image-resize-handle";
  resizeHandle.hidden = true;
  resizeHandle.setAttribute("aria-label", "Przeciągnij, aby zmienić szerokość obrazu");
  editor.append(resizeHandle);

  const clampPixelWidth = (value) => Math.max(1, Math.min(2400, Math.round(Number(value) || 320)));
  const imageSettings = () => ({
    size: ["original", "small", "medium", "large", "wide", "custom"].includes(imageSize?.value || "") ? imageSize.value : "original",
    align: ["none", "left", "right", "center"].includes(imageAlign?.value || "") ? imageAlign.value : "none",
    width: richtextNormalizeImageWidth(imageWidth?.value || "") || "24rem",
  });
  const applyImageSettings = (image, settings) => {
    if (!image) return;
    image.classList.remove(
      "content-image-small",
      "content-image-medium",
      "content-image-large",
      "content-image-wide",
      "content-image-original",
      "content-image-custom",
      "content-image-left",
      "content-image-right",
      "content-image-center"
    );
    image.classList.add("content-image");
    if (settings.align !== "none") image.classList.add(`content-image-${settings.align}`);
    if (settings.size === "custom") {
      image.classList.add("content-image-custom");
      image.style.setProperty("--content-image-width", settings.width);
    } else {
      image.classList.add(`content-image-${settings.size}`);
      image.style.removeProperty("--content-image-width");
      if (!image.getAttribute("style")) image.removeAttribute("style");
    }
  };
  const positionResizeHandle = () => {
    if (!selectedImage || selectedImage.hidden || surface.hidden) {
      resizeHandle.hidden = true;
      return;
    }
    const imageRect = selectedImage.getBoundingClientRect();
    const editorRect = editor.getBoundingClientRect();
    resizeHandle.hidden = false;
    resizeHandle.style.left = `${imageRect.right - editorRect.left - 8 + editor.scrollLeft}px`;
    resizeHandle.style.top = `${imageRect.bottom - editorRect.top - 8 + editor.scrollTop}px`;
  };
  const selectImage = (image) => {
    surface.querySelectorAll(".content-image.is-selected").forEach((item) => item.classList.remove("is-selected"));
    selectedImage = image;
    if (!selectedImage) {
      resizeHandle.hidden = true;
      return;
    }
    selectedImage.classList.add("is-selected");
    const size = ["original", "small", "medium", "large", "wide", "custom"]
      .find((item) => selectedImage.classList.contains(`content-image-${item}`)) || "original";
    const align = ["left", "right", "center"]
      .find((item) => selectedImage.classList.contains(`content-image-${item}`)) || "none";
    if (imageSize) imageSize.value = size;
    if (imageAlign) imageAlign.value = align;
    if (imageWidth) imageWidth.value = richtextImageWidthFromElement(selectedImage) || `${clampPixelWidth(selectedImage.getBoundingClientRect().width)}px`;
    positionResizeHandle();
  };
  const insertImage = (url, alt = "") => {
    const cleanUrl = (url || "").trim();
    if (!/^(https?:\/\/|\/)[^\s<>"']+$/i.test(cleanUrl) || cleanUrl.startsWith("//")) {
      window.alert("Podaj bezpieczny adres obrazu HTTPS albo lokalną ścieżkę /uploads/...");
      return;
    }
    const settings = imageSettings();
    const cleanAlt = (alt || "").trim().slice(0, 255);
    if (formatInput.value === "markdown") {
      const insert = richtextImageMarkdown(cleanUrl, cleanAlt, settings.size, settings.align, settings.width);
      const start = markdown.selectionStart ?? markdown.value.length;
      const end = markdown.selectionEnd ?? markdown.value.length;
      markdown.value = `${markdown.value.slice(0, start)}${insert}${markdown.value.slice(end)}`;
      markdown.focus();
      markdown.setSelectionRange(start + insert.length, start + insert.length);
    } else {
      surface.focus();
      const sizeClass = settings.size === "custom" ? "content-image-custom" : `content-image-${settings.size}`;
      const alignClass = settings.align === "none" ? "" : ` content-image-${settings.align}`;
      const image = `<img src="${escapeHtml(cleanUrl)}" alt="${escapeHtml(cleanAlt)}" class="content-image ${sizeClass}${alignClass}"${settings.size === "custom" ? ` style="--content-image-width:${settings.width};"` : ""} loading="lazy">`;
      document.execCommand("insertHTML", false, image);
    }
    sync();
  };

  [imageSize, imageAlign, imageWidth].forEach((control) => {
    control?.addEventListener("input", () => {
      if (control === imageWidth && imageSize) imageSize.value = "custom";
      if (selectedImage) {
        applyImageSettings(selectedImage, imageSettings());
        sync();
        positionResizeHandle();
      }
    });
    control?.addEventListener("change", () => {
      if (control === imageWidth && imageWidth) {
        const normalized = richtextNormalizeImageWidth(imageWidth.value);
        if (normalized) imageWidth.value = normalized;
      }
      if (selectedImage) {
        applyImageSettings(selectedImage, imageSettings());
        sync();
        positionResizeHandle();
      }
    });
  });

  editor.querySelector("[data-richtext-image-url]")?.addEventListener("click", () => {
    const url = window.prompt("Adres obrazu HTTPS albo lokalna ścieżka /uploads/...");
    if (!url) return;
    insertImage(url, window.prompt("Tekst alternatywny obrazu") || "");
  });
  editor.querySelector("[data-richtext-image-upload]")?.addEventListener("click", () => imageFile?.click());
  imageFile?.addEventListener("change", async () => {
    const file = imageFile.files?.[0];
    if (!file) return;
    const formData = new FormData();
    const alt = window.prompt("Tekst alternatywny obrazu") || "";
    formData.append("asset", file);
    formData.append("alt_text", alt);
    formData.append("title", alt || file.name);
    formData.append("_token", editor.dataset.richtextToken || "");
    try {
      const response = await fetch(editor.dataset.richtextUploadUrl || "", {
        method: "POST",
        body: formData,
        headers: { "Accept": "application/json" },
      });
      const payload = await response.json();
      if (!response.ok || !payload.ok || !payload.url) {
        throw new Error(payload.message || "Nie udało się wgrać obrazu.");
      }
      insertImage(payload.url, payload.alt || alt);
    } catch (error) {
      window.alert(error instanceof Error ? error.message : "Nie udało się wgrać obrazu.");
    } finally {
      imageFile.value = "";
    }
  });

  surface.addEventListener("input", sync);
  surface.addEventListener("click", (event) => {
    const image = event.target instanceof HTMLImageElement && event.target.classList.contains("content-image")
      ? event.target
      : null;
    selectImage(image);
  });
  resizeHandle.addEventListener("pointerdown", (event) => {
    if (!selectedImage) return;
    event.preventDefault();
    resizeHandle.setPointerCapture(event.pointerId);
    const imageRect = selectedImage.getBoundingClientRect();
    const onMove = (moveEvent) => {
      const width = `${clampPixelWidth(moveEvent.clientX - imageRect.left)}px`;
      if (imageSize) imageSize.value = "custom";
      if (imageWidth) imageWidth.value = width;
      applyImageSettings(selectedImage, imageSettings());
      sync();
      positionResizeHandle();
    };
    const onUp = () => {
      resizeHandle.removeEventListener("pointermove", onMove);
      resizeHandle.removeEventListener("pointerup", onUp);
      resizeHandle.removeEventListener("pointercancel", onUp);
    };
    resizeHandle.addEventListener("pointermove", onMove);
    resizeHandle.addEventListener("pointerup", onUp);
    resizeHandle.addEventListener("pointercancel", onUp);
  });
  surface.addEventListener("scroll", positionResizeHandle);
  window.addEventListener("resize", positionResizeHandle);
  markdown.addEventListener("keydown", (event) => {
    if (event.key !== "Enter" || event.shiftKey || event.ctrlKey || event.metaKey || event.altKey || event.isComposing) return;
    if (markdown.selectionStart !== markdown.selectionEnd) return;
    const cursor = markdown.selectionStart;
    const start = markdown.value.lastIndexOf("\n", cursor - 1) + 1;
    const line = markdown.value.slice(start, cursor);
    // Do not interpret sample lists inside fenced code as editable lists.
    if ((markdown.value.slice(0, start).match(/^\s*```/gm) || []).length % 2) return;
    const item = line.match(/^( {0,3})([-+*]|(\d+)[.)]) (.*)$/);
    if (!item) return;
    event.preventDefault();
    if (!item[4].trim()) {
      markdown.setRangeText("", start, cursor, "end");
    } else {
      const marker = item[3] ? `${Number(item[3]) + 1}${item[2].slice(-1)}` : item[2];
      markdown.setRangeText(`\n${item[1]}${marker} `, cursor, cursor, "end");
      if (item[3]) {
        const caret = markdown.selectionStart;
        let number = Number(item[3]) + 1;
        const tail = markdown.value.slice(caret).split("\n");
        for (let index = 1; index < tail.length; index += 1) {
          const next = tail[index].match(/^( {0,3})(\d+)([.)]) (.*)$/);
          if (!next || next[1] !== item[1]) break;
          tail[index] = `${next[1]}${++number}${next[3]} ${next[4]}`;
        }
        markdown.setRangeText(tail.join("\n"), caret, markdown.value.length, "start");
      }
    }
    markdown.dispatchEvent(new Event("input", { bubbles: true }));
  });
  markdown.addEventListener("input", sync);
  surface.addEventListener("paste", (event) => {
    event.preventDefault();
    const text = event.clipboardData?.getData("text/plain") || "";
    const selection = window.getSelection();
    const anchor = selection?.anchorNode;
    const element = anchor?.nodeType === Node.ELEMENT_NODE ? anchor : anchor?.parentElement;
    if (element?.closest("li") && !element.closest("pre, code")) {
      // insertText with newlines creates line breaks within one item in Chromium.
      // Native paragraph insertion splits the item and retains browser undo.
      text.replace(/\r\n?/g, "\n").split("\n").forEach((line, index) => {
        if (index) document.execCommand("insertParagraph", false);
        if (line) document.execCommand("insertText", false, line);
      });
    } else {
      document.execCommand("insertText", false, text);
    }
    sync();
  });
  surface.closest("form")?.addEventListener("submit", sync);
  editor.refreshRichtext = () => {
    documentFormat = formatInput.value === "markdown" ? "markdown" : "html";
    content = input.value;
    setMode(documentFormat, false);
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

document.querySelectorAll(".admin-filter-modal").forEach((modal) => {
  document.body.append(modal);
});

document.querySelectorAll('form[action*="/admin/server-provisioner/install"]').forEach((form) => {
  const engine = form.querySelector('[name="engine"]');
  const version = form.querySelector('[name="minecraft_version"]');
  const build = form.querySelector('[name="requested_build_id"]');
  const commonPlugins = Array.from(form.querySelectorAll('input[name="common_plugins[]"]'));
  const pluginBuilds = form.querySelector('[name="plugin_builds[]"]');
  const onlineMode = form.querySelector('[name="online_mode"]');
  const acceptEula = form.querySelector('[name="accept_eula"]');
  if (!(engine instanceof HTMLSelectElement) || !(version instanceof HTMLSelectElement) || !(build instanceof HTMLSelectElement)) return;
  const syncCommonPlugins = () => {
    const proxy = ["velocity", "bungeecord"].includes(engine.value);
    const supported = !proxy && ["bukkit", "craftbukkit", "spigot", "paper", "folia", "purpur", "pufferfish", "leaves"].includes(engine.value);
    commonPlugins.forEach((input) => {
      input.disabled = !supported;
      if (!supported) input.checked = false;
    });
    commonPlugins[0]?.closest(".mb-3")?.toggleAttribute("hidden", proxy);
    pluginBuilds?.closest(".mb-3")?.toggleAttribute("hidden", proxy);
    if (onlineMode instanceof HTMLInputElement) onlineMode.checked = !proxy;
    if (acceptEula instanceof HTMLInputElement) {
      acceptEula.disabled = proxy;
      acceptEula.required = !proxy;
      if (proxy) acceptEula.checked = false;
      acceptEula.closest(".mb-3")?.toggleAttribute("hidden", proxy);
    }
    const versionLabel = version.closest(".mb-3")?.querySelector("label");
    if (versionLabel) versionLabel.childNodes[0].textContent = engine.value === "velocity" ? "Wersja Velocity" : (proxy ? "Wersja docelowa Minecraft" : "Wersja Minecraft");
  };
  const fill = (select, options, emptyLabel, reverseNumeric = false) => {
    select.replaceChildren(new Option(emptyLabel, ""));
    const entries = Object.entries(options || {});
    if (reverseNumeric) entries.reverse();
    entries.forEach(([value, label]) => select.add(new Option(String(label), value)));
  };
  const loadBuilds = async () => {
    fill(build, {}, "Najnowszy dostępny build");
    if (!version.value) return;
    build.disabled = true;
    try {
      const response = await fetch(`index.php?route=/admin/server-provisioner/catalog&engine=${encodeURIComponent(engine.value)}&version=${encodeURIComponent(version.value)}`, { headers: { Accept: "application/json" } });
      const data = await response.json();
      if (!response.ok || !data.ok) throw new Error(data.message || "Błąd katalogu");
      fill(build, data.options, Object.keys(data.options || {}).length ? "Najnowszy dostępny build" : "Provider wybierze aktualny build", true);
    } catch {
      fill(build, {}, "Nie udało się pobrać buildów");
    } finally {
      build.disabled = false;
    }
  };
  const loadVersions = async () => {
    version.disabled = true;
    fill(version, {}, "Ładowanie katalogu wersji…");
    fill(build, {}, "Najnowszy dostępny build");
    try {
      const response = await fetch(`index.php?route=/admin/server-provisioner/catalog&engine=${encodeURIComponent(engine.value)}`, { headers: { Accept: "application/json" } });
      const data = await response.json();
      if (!response.ok || !data.ok) throw new Error(data.message || "Błąd katalogu");
      fill(version, data.options, "Wybierz istniejącą wersję");
    } catch {
      fill(version, {}, "Katalog wersji jest chwilowo niedostępny");
    } finally {
      version.disabled = false;
    }
  };
  engine.addEventListener("change", () => {
    syncCommonPlugins();
    loadVersions();
  });
  version.addEventListener("change", loadBuilds);
  syncCommonPlugins();
  loadVersions();
});

document.querySelectorAll('form[action*="/admin/server-provisioner/update"]').forEach((form) => {
  const engine = form.querySelector('[name="engine"]');
  const version = form.querySelector('[name="minecraft_version"]');
  const build = form.querySelector('[name="requested_build_id"]');
  const currentVersion = form.querySelector('[name="current_version"]');
  const currentBuild = form.querySelector('[name="current_build_id"]');
  if (
    !(engine instanceof HTMLInputElement)
    || !(version instanceof HTMLSelectElement)
    || !(build instanceof HTMLSelectElement)
    || !(currentVersion instanceof HTMLInputElement)
    || !(currentBuild instanceof HTMLInputElement)
  ) return;
  const loadBuilds = async () => {
    build.disabled = true;
    build.replaceChildren(new Option("Ładowanie propozycji buildów…", ""));
    try {
      const response = await fetch(`index.php?route=/admin/server-provisioner/catalog&engine=${encodeURIComponent(engine.value)}&version=${encodeURIComponent(version.value)}`, { headers: { Accept: "application/json" } });
      const data = await response.json();
      if (!response.ok || !data.ok) throw new Error(data.message || "Błąd katalogu");
      const catalogEntries = Object.entries(data.options || {});
      let entries = catalogEntries;
      const installed = Number.parseInt(currentBuild.value, 10) || 0;
      if (version.value === currentVersion.value) {
        entries = entries.filter(([id]) => installed <= 0 || Number.parseInt(id, 10) > installed);
      }
      entries.reverse();
      build.replaceChildren();
      if (entries.length === 0) {
        const noNewerBuild = version.value === currentVersion.value && installed > 0 && catalogEntries.length > 0;
        build.add(new Option(noNewerBuild ? "Brak nowszego buildu — wybierz inną wersję" : "Provider wybierze najnowszy dostępny artefakt", ""));
      } else {
        entries.forEach(([value, label]) => build.add(new Option(String(label), value)));
      }
    } catch {
      build.replaceChildren(new Option("Nie udało się pobrać propozycji buildów", ""));
    } finally {
      build.disabled = false;
    }
  };
  version.addEventListener("change", loadBuilds);
});

document.querySelectorAll("[data-plugin-build-item]").forEach((item) => {
  const enabled = item.querySelector("[data-plugin-build-enable]");
  const version = item.querySelector("[data-plugin-build-version]");
  const build = item.querySelector("[data-plugin-build-select]");
  if (!(enabled instanceof HTMLInputElement) || !(version instanceof HTMLSelectElement) || !(build instanceof HTMLSelectElement)) return;
  const allBuilds = Array.from(build.options).map((option) => option.cloneNode(true));
  const refresh = () => {
    const selected = version.value;
    build.replaceChildren(...allBuilds.filter((option) => option.dataset.pluginBuildVersionValue === selected).map((option) => option.cloneNode(true)));
  };
  enabled.addEventListener("change", () => {
    version.disabled = !enabled.checked;
    build.disabled = !enabled.checked;
    if (enabled.checked) refresh();
  });
  version.addEventListener("change", refresh);
  refresh();
});
