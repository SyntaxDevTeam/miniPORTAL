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
