(() => {
  "use strict";

  const SHOW_DELAY_MS = 150;
  const SLOW_NOTICE_MS = 8000;
  let showTimer = 0;
  let slowTimer = 0;
  let indicator = null;
  let label = null;
  let busyTarget = null;
  const pendingForms = new Set();

  const language = (document.documentElement.lang || "").toLowerCase();
  const loadingLabel = language.startsWith("pl") ? "Ładowanie…" : "Loading…";
  const slowLabel = language.startsWith("pl")
    ? "Ładowanie trwa dłużej niż zwykle…"
    : "Loading is taking longer than usual…";

  const createIndicator = () => {
    if (indicator) return indicator;

    indicator = document.createElement("div");
    indicator.className = "page-loading";
    indicator.dataset.pageLoading = "";
    indicator.hidden = true;
    indicator.setAttribute("role", "status");
    indicator.setAttribute("aria-live", "polite");
    indicator.setAttribute("aria-atomic", "true");

    const status = document.createElement("span");
    status.className = "page-loading__status";
    const spinner = document.createElement("span");
    spinner.className = "page-loading__indicator";
    spinner.setAttribute("aria-hidden", "true");
    label = document.createElement("span");
    label.textContent = loadingLabel;
    status.append(spinner, label);
    indicator.append(status);
    document.body.append(indicator);

    return indicator;
  };

  const show = () => {
    window.clearTimeout(showTimer);
    window.clearTimeout(slowTimer);
    showTimer = window.setTimeout(() => {
      const element = createIndicator();
      if (label) label.textContent = loadingLabel;
      element.hidden = false;
      document.documentElement.classList.add("is-page-loading");
      busyTarget = document.querySelector("main") || document.body;
      busyTarget?.setAttribute("aria-busy", "true");
      slowTimer = window.setTimeout(() => {
        if (label && !element.hidden) label.textContent = slowLabel;
      }, SLOW_NOTICE_MS);
    }, SHOW_DELAY_MS);
  };

  const hide = () => {
    window.clearTimeout(showTimer);
    window.clearTimeout(slowTimer);
    showTimer = 0;
    slowTimer = 0;
    if (indicator) indicator.hidden = true;
    if (label) label.textContent = loadingLabel;
    document.documentElement.classList.remove("is-page-loading");
    busyTarget?.removeAttribute("aria-busy");
    busyTarget = null;
    pendingForms.forEach((form) => delete form.dataset.pageLoadingPending);
    pendingForms.clear();
  };

  const isDownloadPath = (url) => {
    const value = `${url.pathname}${url.search}`.toLowerCase();
    return value.includes("/download")
      || value.includes("/export")
      || value.includes("route=%2fapi%2f")
      || value.includes("route=/api/");
  };

  const navigatesDocument = (anchor, event) => {
    if (event.button !== 0 || event.defaultPrevented) return false;
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return false;
    if (anchor.hasAttribute("download") || anchor.dataset.noLoading !== undefined) return false;
    if (anchor.target && anchor.target.toLowerCase() !== "_self") return false;
    if (anchor.getAttribute("href")?.startsWith("#")) return false;

    let url;
    try {
      url = new URL(anchor.href, window.location.href);
    } catch {
      return false;
    }
    if (!['http:', 'https:'].includes(url.protocol) || url.origin !== window.location.origin) return false;
    if (isDownloadPath(url)) return false;
    return !(url.pathname === window.location.pathname
      && url.search === window.location.search
      && url.hash !== "");
  };

  document.addEventListener("click", (event) => {
    const target = event.target;
    const anchor = target instanceof Element ? target.closest("a[href]") : null;
    if (anchor instanceof HTMLAnchorElement && navigatesDocument(anchor, event)) {
      show();
      queueMicrotask(() => { if (event.defaultPrevented) hide(); });
    }
  }, true);

  document.addEventListener("submit", (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || event.defaultPrevented) return;
    if (form.dataset.noLoading !== undefined || (form.target && form.target.toLowerCase() !== "_self")) return;
    if (form.dataset.pageLoadingPending === "1") {
      event.preventDefault();
      return;
    }
    try {
      const url = new URL(form.action || window.location.href, window.location.href);
      if (url.origin !== window.location.origin || isDownloadPath(url)) return;
    } catch {
      return;
    }
    form.dataset.pageLoadingPending = "1";
    pendingForms.add(form);
    show();
    queueMicrotask(() => { if (event.defaultPrevented) hide(); });
  }, true);

  document.addEventListener("htmx:beforeRequest", show);
  document.addEventListener("htmx:afterRequest", hide);
  document.addEventListener("htmx:responseError", hide);
  window.addEventListener("pageshow", hide);
  window.addEventListener("DOMContentLoaded", createIndicator, { once: true });
})();
