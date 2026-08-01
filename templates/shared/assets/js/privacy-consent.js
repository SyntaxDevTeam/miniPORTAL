(() => {
  "use strict";

  const storageKey = "miniportal.cookie-consent.v2";
  const panel = document.querySelector("[data-cookie-consent]");

  if (!panel) {
    return;
  }

  const summary = panel.querySelector("[data-cookie-summary]");
  const settings = panel.querySelector("[data-cookie-settings]");
  const analyticsInput = panel.querySelector("[data-cookie-category=\"analytics\"]");
  const storageError = panel.querySelector("[data-cookie-storage-error]");
  const analyticsAvailable = panel.dataset.analyticsAvailable === "1";

  const readConsent = () => {
    try {
      const stored = window.localStorage.getItem(storageKey);
      if (stored === null) {
        return null;
      }

      const consent = JSON.parse(stored);
      if (
        typeof consent !== "object"
        || consent === null
        || consent.necessary !== true
        || typeof consent.analytics !== "boolean"
      ) {
        return null;
      }

      return {
        necessary: true,
        analytics: analyticsAvailable && consent.analytics,
      };
    } catch (_error) {
      return null;
    }
  };

  const saveConsent = (analytics) => {
    const consent = {
      necessary: true,
      analytics: analyticsAvailable && analytics,
    };

    try {
      const encoded = JSON.stringify(consent);
      window.localStorage.setItem(storageKey, encoded);
      if (window.localStorage.getItem(storageKey) !== encoded) {
        return null;
      }
      window.localStorage.removeItem("disable-faststats");
      return consent;
    } catch (_error) {
      return null;
    }
  };

  const showStorageError = () => {
    if (storageError) {
      storageError.hidden = false;
    }
  };

  const showSummary = (moveFocus = true) => {
    if (summary) {
      summary.hidden = false;
    }
    if (settings) {
      settings.hidden = true;
    }
    panel.hidden = false;
    if (moveFocus) {
      panel.querySelector("[data-cookie-accept-all]")?.focus();
    }
  };

  const showSettings = () => {
    const consent = readConsent();
    if (analyticsInput instanceof HTMLInputElement) {
      analyticsInput.checked = analyticsAvailable && consent?.analytics === true;
      analyticsInput.disabled = !analyticsAvailable;
    }
    if (summary) {
      summary.hidden = true;
    }
    if (settings) {
      settings.hidden = false;
    }
    panel.hidden = false;
    if (analyticsInput instanceof HTMLInputElement && !analyticsInput.disabled) {
      analyticsInput.focus();
    } else {
      panel.querySelector("[data-cookie-save-settings]")?.focus();
    }
  };

  const applyConsent = (analytics) => {
    const previous = readConsent();
    const saved = saveConsent(analytics);
    if (saved === null) {
      showStorageError();
      return;
    }

    panel.hidden = true;
    if (saved.analytics || previous?.analytics !== saved.analytics) {
      window.location.reload();
    }
  };

  panel.querySelector("[data-cookie-accept-all]")?.addEventListener("click", () => {
    applyConsent(true);
  });

  panel.querySelectorAll("[data-cookie-reject-optional]").forEach((button) => {
    button.addEventListener("click", () => {
      applyConsent(false);
    });
  });

  panel.querySelector("[data-cookie-open-settings]")?.addEventListener("click", showSettings);
  panel.querySelector("[data-cookie-back]")?.addEventListener("click", () => showSummary());
  panel.querySelector("[data-cookie-save-settings]")?.addEventListener("click", () => {
    applyConsent(analyticsInput instanceof HTMLInputElement && analyticsInput.checked);
  });

  document.querySelectorAll("[data-cookie-consent-manage]").forEach((button) => {
    button.addEventListener("click", showSettings);
  });

  if (readConsent() === null) {
    showSummary(false);
  }
})();
