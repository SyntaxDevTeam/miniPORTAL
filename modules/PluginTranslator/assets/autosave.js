(function () {
  "use strict";

  const storagePrefix = "miniportal:plugin-translator:v1:";
  const saveDelay = 500;

  const forms = Array.from(document.querySelectorAll("form[data-translation-autosave]"));
  const decisionForms = Array.from(document.querySelectorAll("form.translation-decision"));

  const storageAvailable = () => {
    try {
      const probe = `${storagePrefix}probe`;
      window.localStorage.setItem(probe, "1");
      window.localStorage.removeItem(probe);
      return true;
    } catch (error) {
      return false;
    }
  };

  if (!storageAvailable()) {
    return;
  }

  const storageKey = (key) => `${storagePrefix}${key}`;

  const formatTime = (timestamp) => {
    try {
      return new Date(timestamp).toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" });
    } catch (error) {
      return "";
    }
  };

  const showStatus = (form, message, canClear) => {
    const status = form.querySelector("[data-autosave-status]");
    if (!status) {
      return;
    }
    status.hidden = false;
    status.textContent = message;
    if (canClear) {
      const button = document.createElement("button");
      button.type = "button";
      button.className = "btn btn-sm btn-outline-light ms-2";
      button.textContent = "Clear autosave";
      button.addEventListener("click", () => {
        window.localStorage.removeItem(storageKey(form.dataset.autosaveKey || ""));
        status.hidden = true;
      });
      status.appendChild(button);
    }
  };

  const fields = (form) => Array.from(form.querySelectorAll("[name^='translations[']"));

  const readValues = (form) => {
    const values = {};
    fields(form).forEach((field) => {
      values[field.name] = field.value;
    });
    return values;
  };

  const hasUsefulValues = (values) => Object.values(values).some((value) => value.trim() !== "");

  const applyValues = (form, values) => {
    fields(form).forEach((field) => {
      if (Object.prototype.hasOwnProperty.call(values, field.name)) {
        field.value = values[field.name];
      }
    });
  };

  forms.forEach((form) => {
    const key = form.dataset.autosaveKey || "";
    if (!key) {
      return;
    }

    let saveTimer = 0;
    const keyName = storageKey(key);

    try {
      const saved = JSON.parse(window.localStorage.getItem(keyName) || "null");
      if (saved && saved.values && hasUsefulValues(saved.values)) {
        applyValues(form, saved.values);
        const savedAt = typeof saved.savedAt === "number" ? saved.savedAt : Date.now();
        showStatus(form, `Autosaved work restored from ${formatTime(savedAt)}.`, true);
      } else {
        showStatus(form, "Autosave is active. Your work is stored in this browser while you type.", false);
      }
    } catch (error) {
      window.localStorage.removeItem(keyName);
    }

    const save = () => {
      const values = readValues(form);
      if (!hasUsefulValues(values)) {
        window.localStorage.removeItem(keyName);
        return;
      }
      window.localStorage.setItem(keyName, JSON.stringify({ savedAt: Date.now(), values }));
      showStatus(form, `Autosaved at ${formatTime(Date.now())}.`, false);
    };

    form.addEventListener("input", (event) => {
      if (!(event.target instanceof HTMLInputElement) && !(event.target instanceof HTMLTextAreaElement)) {
        return;
      }
      window.clearTimeout(saveTimer);
      saveTimer = window.setTimeout(save, saveDelay);
    });

    form.addEventListener("submit", () => {
      window.clearTimeout(saveTimer);
      save();
    });

    window.addEventListener("beforeunload", () => {
      window.clearTimeout(saveTimer);
      save();
    });
  });

  decisionForms.forEach((form) => {
    const input = form.querySelector("input[name='autosave_key']");
    const key = input ? input.value : "";
    if (!key) {
      return;
    }

    form.addEventListener("submit", (event) => {
      const submitter = event.submitter instanceof HTMLButtonElement ? event.submitter : null;
      const action = submitter ? submitter.value : "";
      if (["save_draft", "submit_review", "discard"].includes(action)) {
        window.localStorage.removeItem(storageKey(key));
      }
    });
  });

  document.querySelectorAll("form[data-translation-decision]").forEach((form) => {
    const owner = form.querySelector("[data-decision-owner]");
    const syntaxFields = form.querySelector("[data-syntaxdevteam-fields]");
    const reviewButton = form.querySelector("[data-review-action]");
    const note = form.querySelector("[data-decision-note]");
    const complete = form.dataset.complete === "1";

    if (!(owner instanceof HTMLInputElement) || !syntaxFields || !(reviewButton instanceof HTMLButtonElement)) {
      return;
    }

    const syntaxControls = Array.from(syntaxFields.querySelectorAll("input, select, textarea, button"));
    const updateDecision = () => {
      const syntaxDevTeam = owner.checked;
      syntaxFields.hidden = !syntaxDevTeam;
      syntaxFields.setAttribute("aria-hidden", syntaxDevTeam ? "false" : "true");
      syntaxControls.forEach((control) => {
        control.disabled = !syntaxDevTeam;
      });

      reviewButton.disabled = !syntaxDevTeam || !complete;
      if (!syntaxDevTeam) {
        reviewButton.title = "Review is available only for SyntaxDevTeam plugin translations.";
        if (note) {
          note.textContent = "Third-party plugin translation: download the YAML now or keep it as your private draft.";
        }
      } else if (!complete) {
        reviewButton.title = "Complete every translation line before submitting for review.";
        if (note) {
          note.textContent = "SyntaxDevTeam review becomes available after every line is translated.";
        }
      } else {
        reviewButton.title = "";
        if (note) {
          note.textContent = "SyntaxDevTeam plugin translation: choose a category and submit it for review when ready.";
        }
      }
    };

    owner.addEventListener("change", updateDecision);
    updateDecision();
  });
})();
