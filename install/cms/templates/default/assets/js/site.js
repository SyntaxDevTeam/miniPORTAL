const navigation = document.querySelector("[data-site-nav]");

const updateNavigation = () => {
  navigation?.classList.toggle("is-scrolled", window.scrollY > 16);
};

updateNavigation();
window.addEventListener("scroll", updateNavigation, { passive: true });

document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
  anchor.addEventListener("click", () => {
    const menu = document.querySelector(".navbar-collapse.show");

    if (menu && window.bootstrap) {
      window.bootstrap.Collapse.getOrCreateInstance(menu).hide();
    }

    const targetId = decodeURIComponent(anchor.hash.slice(1));
    const target = targetId === "" ? null : document.getElementById(targetId);
    if (!target) {
      return;
    }

    const hadTabindex = target.hasAttribute("tabindex");
    if (!hadTabindex) {
      target.setAttribute("tabindex", "-1");
    }
    window.requestAnimationFrame(() => {
      target.focus({ preventScroll: true });
      if (!hadTabindex) {
        target.addEventListener("blur", () => target.removeAttribute("tabindex"), { once: true });
      }
    });
  });
});

const revealElements = document.querySelectorAll(".reveal");
const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

if (reduceMotion || !("IntersectionObserver" in window)) {
  revealElements.forEach((element) => element.classList.add("is-visible"));
} else {
  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) {
          return;
        }

        entry.target.classList.add("is-visible");
        observer.unobserve(entry.target);
      });
    },
    { threshold: 0.12 }
  );

  revealElements.forEach((element) => observer.observe(element));
}

const terminal = document.querySelector("[data-home-terminal]");

if (terminal) {
  const output = terminal.querySelector("[data-terminal-output]");
  const form = terminal.querySelector("[data-terminal-form]");
  const input = terminal.querySelector("[data-terminal-input]");
  const authenticated = terminal.dataset.authenticated === "true";
  const history = [];
  let historyIndex = 0;

  const statusLines = [
    ["CoreAuth        ", "READY"],
    ["CorePages       ", "READY"],
    ["ThemeEngine     ", "ONLINE"],
    ["SyntaxCrudApp   ", "CONNECTED"],
    ["architecture:   ", "MODULAR"],
    ["security:       ", "ENABLED"],
    ["status:         ", "READY_TO_USE"],
  ];

  const bootSteps = [
    { text: "Uruchamianie SyntaxDevTerminal..." },
    ...statusLines.map(([label, value]) => ({ label, value })),
    {
      text: "Witaj w  SyntaxDevTerminal 0.1.5 .\nWpisz help i naciśnij Enter aby zobaczyć dostępne komendy.",
      className: "terminal-welcome",
    },
  ];

  const helpText = [
    "Dostępne komendy:",
    "  help       lista komend",
    "  status     stan miniPORTAL",
    "  ls         publiczne obszary serwisu",
    "  cd <nazwa> otwórz: projects, downloads, wiki, team lub home",
    "  login      logowanie albo panel użytkownika",
    "  projects   katalog projektów",
    "  download   pliki do pobrania",
    "  wiki       dokumentacja projektów",
    "  team       zespół SyntaxDevTeam",
    "  about      informacje o miniPORTAL",
    "  whoami     informacje o bieżącej sesji",
    "  clear      wyczyść ekran",
  ].join("\n");

  const routes = {
    home: "/",
    projects: "/projects",
    project: "/projects",
    downloads: "/builds",
    download: "/builds",
    builds: "/builds",
    wiki: "/wiki",
    docs: "/wiki",
    team: "/team",
    about: "/p/miniportal",
    login: authenticated ? "/admin" : "/admin/login",
  };

  const appendLine = (text, className = "") => {
    const pre = document.createElement("pre");
    const code = document.createElement("code");
    code.textContent = text;
    if (className) {
      pre.className = className;
    }
    pre.append(code);
    output.append(pre);
    output.scrollTop = output.scrollHeight;
  };

  const appendStatus = (label, value) => {
    const pre = document.createElement("pre");
    const code = document.createElement("code");
    const status = document.createElement("span");
    status.className = "terminal-status";
    status.textContent = value;
    code.append(document.createTextNode(label), status);
    pre.append(code);
    output.append(pre);
    output.scrollTop = output.scrollHeight;
  };

  const navigate = (route, label) => {
    appendLine(`Otwieram: ${label} (${route})`, "terminal-info");
    window.setTimeout(() => window.location.assign(route), reduceMotion ? 0 : 260);
  };

  const execute = (rawCommand) => {
    const command = rawCommand.trim();
    if (!command) {
      return;
    }

    appendLine(`visitor@syntax:~$ ${command}`, "terminal-entry");
    const [name = "", ...args] = command.toLowerCase().split(/\s+/);

    if (name === "clear" || name === "cls") {
      output.replaceChildren();
      return;
    }
    if (name === "help" || name === "man") {
      appendLine(helpText);
      return;
    }
    if (name === "status" || command === "./workspace status") {
      statusLines.forEach(([label, value]) => appendStatus(label, value));
      return;
    }
    if (name === "ls") {
      appendLine("projects/  downloads/  wiki/  team/  about/  login/");
      return;
    }
    if (name === "pwd") {
      appendLine("/home/visitor/syntaxdevteam");
      return;
    }
    if (name === "whoami") {
      appendLine(authenticated ? "authenticated-user" : "visitor (sesja publiczna)");
      return;
    }
    if (name === "cd") {
      const target = (args[0] || "home").replace(/^\.\//, "").replace(/\/$/, "");
      if (routes[target]) {
        navigate(routes[target], target);
      } else {
        appendLine(`cd: ${target || "~"}: brak takiego obszaru. Użyj ls.`, "terminal-error");
      }
      return;
    }
    if (routes[name]) {
      navigate(routes[name], name);
      return;
    }

    appendLine(`${name}: command not found. Wpisz help.`, "terminal-error");
  };

  const startTerminal = async () => {
    input.disabled = true;
    output.replaceChildren();
    for (const step of bootSteps) {
      if (step.label) {
        appendStatus(step.label, step.value);
      } else {
        appendLine(step.text, step.className || "");
      }
      if (!reduceMotion) {
        await new Promise((resolve) => window.setTimeout(resolve, step.className ? 70 : 135));
      }
    }
    input.disabled = false;
  };

  form.addEventListener("submit", (event) => {
    event.preventDefault();
    const command = input.value;
    if (command.trim()) {
      history.push(command);
      historyIndex = history.length;
    }
    input.value = "";
    execute(command);
  });

  input.addEventListener("keydown", (event) => {
    if (event.key !== "ArrowUp" && event.key !== "ArrowDown") {
      return;
    }
    event.preventDefault();
    historyIndex += event.key === "ArrowUp" ? -1 : 1;
    historyIndex = Math.max(0, Math.min(history.length, historyIndex));
    input.value = history[historyIndex] || "";
    window.requestAnimationFrame(() => input.setSelectionRange(input.value.length, input.value.length));
  });

  terminal.addEventListener("click", (event) => {
    if (!event.target.closest("a, button")) {
      input.focus();
    }
  });

  startTerminal();
}
