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

const inlineMarkdownToHtml = (value) => escapeHtml(value)
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
        output.push(`<pre><code>${escapeHtml(code.join("\n"))}</code></pre>`);
        code = null;
      } else {
        code.push(line);
      }
      continue;
    }
    if (/^ {0,3}```/.test(line)) {
      flushParagraph();
      closeList();
      code = [];
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
      case "table": {
        const rows = [...node.querySelectorAll("tr")]
          .map((row) => [...row.children]
            .filter((cell) => ["TH", "TD"].includes(cell.tagName))
            .map((cell) => [...cell.childNodes]
              .map((child) => walk(child, depth + 1))
              .join("")
              .replace(/\s+/g, " ")
              .trim()
              .replaceAll("|", "\\|")))
          .filter((row) => row.length > 0);
        if (rows.length === 0) return "";
        const columnCount = Math.max(...rows.map((row) => row.length));
        const normalizeRow = (row) => Array.from({ length: columnCount }, (_value, index) => row[index] || "");
        const header = normalizeRow(rows[0]);
        const body = rows.slice(1).map(normalizeRow);
        return [
          `| ${header.join(" | ")} |`,
          `| ${header.map(() => "---").join(" | ")} |`,
          ...body.map((row) => `| ${row.join(" | ")} |`),
        ].join("\n") + "\n\n";
      }
      case "img": {
        const classes = [...node.classList]
          .filter((className) => /^content-image-(left|right|center|wide|original|small|medium|large|custom)$/.test(className))
          .map((className) => `.${className}`)
          .join(" ");
        const width = richtextImageWidthFromElement(node);
        const attrs = [classes, width ? `width=${width}` : ""].filter(Boolean).join(" ");
        return `![${node.getAttribute("alt") || ""}](${node.getAttribute("src") || ""})${attrs ? `{${attrs}}` : ""}`;
      }
      case "li": return `${node.parentElement?.tagName === "OL" ? "1." : "-"} ${content.trim()}\n`;
      case "ul":
      case "ol": return `${content}\n`;
      case "hr": return "---\n\n";
      default: return content;
    }
  };

  return walk(root).replace(/\n{3,}/g, "\n\n").trim();
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
  return richtextNormalizeImageWidth(image.style.getPropertyValue("--content-image-width"));
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

document.querySelectorAll("[data-minecraft-console]").forEach((root) => {
  const output = root.querySelector("[data-console-output]");
  const form = root.querySelector("[data-console-form]");
  if (!output || !form) return;

  let pollTimer = null;
  let knownOutput = "";
  let consoleOffset = 0;
  let streamInitialized = false;

  const applyConsoleOutput = (nextOutput) => {
    if (nextOutput === knownOutput) return;
    const stickToBottom = output.scrollHeight - output.scrollTop - output.clientHeight < 32;
    const previousScrollTop = output.scrollTop;
    output.textContent = nextOutput;
    knownOutput = nextOutput;
    if (stickToBottom) {
      output.scrollTop = output.scrollHeight;
    } else {
      output.scrollTop = previousScrollTop;
    }
  };

  const poll = async () => {
    try {
      const url = new URL(root.dataset.outputUrl || "", window.location.href);
      url.searchParams.set("offset", String(consoleOffset));
      const response = await fetch(url.toString(), {
        cache: "no-store",
        headers: { Accept: "application/json" },
      });
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }
      const data = await response.json();
      if (data.source === "log") {
        const nextOutput = data.output || "";
        const shouldReset = Boolean(data.reset) || !streamInitialized;
        const stickToBottom = output.scrollHeight - output.scrollTop - output.clientHeight < 32;
        if (shouldReset) {
          output.textContent = nextOutput;
        } else if (nextOutput !== "") {
          output.textContent += nextOutput;
        }
        if (output.textContent.length > 262144) {
          output.textContent = output.textContent.slice(-262144);
        }
        consoleOffset = Number.isFinite(Number(data.offset)) ? Number(data.offset) : consoleOffset;
        streamInitialized = true;
        knownOutput = output.textContent;
        if (stickToBottom || shouldReset) {
          output.scrollTop = output.scrollHeight;
        }
      } else {
        streamInitialized = false;
        consoleOffset = 0;
        applyConsoleOutput(data.output || "");
      }
    } catch {
      output.textContent = "Nie udało się pobrać bufora konsoli.";
      knownOutput = "";
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
      if (response.ok && form.command) {
        form.command.value = "";
      }
      window.clearTimeout(pollTimer);
      poll();
    } catch {
      output.textContent = "Nie udało się wysłać komendy do konsoli.";
    }
  });

  poll();
});
