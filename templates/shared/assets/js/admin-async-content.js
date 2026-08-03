(() => {
  const regions = [...document.querySelectorAll("[data-admin-async-url]")];
  if (regions.length === 0) return;

  const load = async (region) => {
    if (region.dataset.adminAsyncLoading === "1") return;
    region.dataset.adminAsyncLoading = "1";
    region.setAttribute("aria-busy", "true");
    const controller = new AbortController();
    const timeout = window.setTimeout(() => controller.abort(), 20000);

    try {
      const response = await fetch(region.dataset.adminAsyncUrl, {
        credentials: "same-origin",
        headers: {
          Accept: "text/html",
          "X-Requested-With": "miniportal-admin-shell",
        },
        signal: controller.signal,
      });
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      const html = await response.text();
      region.innerHTML = html;
      region.dataset.adminAsyncLoaded = "1";
      region.setAttribute("aria-busy", "false");
      document.dispatchEvent(new CustomEvent("miniportal:async-content-loaded", {
        detail: { region },
      }));
    } catch (error) {
      region.replaceChildren();
      const alert = document.createElement("div");
      const message = document.createElement("p");
      const retry = document.createElement("button");
      alert.className = "alert alert-warning";
      alert.setAttribute("role", "alert");
      message.className = "mb-3";
      message.textContent = error?.name === "AbortError"
        ? "Dane pulpitu nie odpowiedziały w wymaganym czasie. Nawigacja panelu nadal działa."
        : "Nie udało się pobrać danych pulpitu. Nawigacja panelu nadal działa.";
      retry.type = "button";
      retry.className = "btn btn-outline-primary";
      retry.textContent = "Spróbuj ponownie";
      retry.addEventListener("click", () => {
        region.dataset.adminAsyncLoading = "0";
        load(region);
      });
      alert.append(message, retry);
      region.append(alert);
      region.setAttribute("aria-busy", "false");
    } finally {
      window.clearTimeout(timeout);
      region.dataset.adminAsyncLoading = "0";
    }
  };

  regions.forEach(load);
})();
