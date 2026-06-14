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

document.querySelectorAll("[data-richtext]").forEach((editor) => {
  const surface = editor.querySelector("[data-richtext-surface]");
  const input = editor.querySelector("[data-richtext-input]");

  if (!surface || !input) {
    return;
  }

  const sync = () => {
    input.value = surface.innerHTML;
  };

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
  surface.addEventListener("paste", (event) => {
    event.preventDefault();
    const text = event.clipboardData?.getData("text/plain") || "";
    document.execCommand("insertText", false, text);
  });
  surface.closest("form")?.addEventListener("submit", sync);
});

document.querySelectorAll('form').forEach((form) => {
  const keyInput = form.querySelector('input[name="_autosave_key"]');
  if (!keyInput?.value) {
    return;
  }

  const storageKey = `miniportal:${keyInput.value}`;
  const clearKey = new URLSearchParams(window.location.search).get("autosave_clear");
  if (clearKey) {
    localStorage.removeItem(`miniportal:${clearKey}`);
    window.history.replaceState({}, "", window.location.href.replace(/([&?])autosave_clear=[^&]*&?/, "$1").replace(/[?&]$/, ""));
  }
  const fields = [...form.elements].filter((field) =>
    field.name && !["_token", "_autosave_key", "id"].includes(field.name)
  );
  let timer = null;
  const status = document.createElement("div");
  status.className = "form-text mt-3";
  status.setAttribute("role", "status");
  form.append(status);

  try {
    const saved = JSON.parse(localStorage.getItem(storageKey) || "null");
    if (saved?.values) {
      fields.forEach((field) => {
        if (!(field.name in saved.values)) return;
        if (field.type === "checkbox") {
          field.checked = Boolean(saved.values[field.name]);
        } else {
          field.value = saved.values[field.name];
        }
        const richtext = field.matches("[data-richtext-input]")
          ? field.closest("[data-richtext]")?.querySelector("[data-richtext-surface]")
          : null;
        if (richtext) richtext.innerHTML = field.value;
      });
      status.textContent = "Przywrócono lokalną wersję roboczą.";
    }
  } catch {
    localStorage.removeItem(storageKey);
  }

  const saveDraft = () => {
    const values = {};
    fields.forEach((field) => {
      values[field.name] = field.type === "checkbox" ? field.checked : field.value;
    });
    localStorage.setItem(storageKey, JSON.stringify({ savedAt: Date.now(), values }));
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
