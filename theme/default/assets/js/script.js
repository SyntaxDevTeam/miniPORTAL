const nav = document.querySelector(".sdt-navbar");

window.addEventListener("scroll", () => {
  if (!nav) return;
  nav.classList.toggle("is-scrolled", window.scrollY > 24);
});

document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
  anchor.addEventListener("click", () => {
    const menu = document.querySelector("#mainNav");
    if (!menu || !menu.classList.contains("show")) return;

    const collapse = bootstrap.Collapse.getOrCreateInstance(menu);
    collapse.hide();
  });
});

const terminal = document.querySelector("[data-terminal-root]");

if (terminal) {
  const output = terminal.querySelector("[data-terminal-output]");
  const form = terminal.querySelector("[data-terminal-form]");
  const input = terminal.querySelector("[data-terminal-input]");
  const prompt = "syntax@devteam:~$";
  const history = [];
  let historyIndex = 0;

  const commandNames = [
    "help",
    "projects",
    "projekty",
    "stack",
    "status",
    "contact",
    "kontakt",
    "github",
    "repo",
    "clear",
    "date",
    "echo",
    "whoami",
  ];

  const appendLine = (text = "", className = "") => {
    if (!output) return;

    const line = document.createElement("div");
    line.className = `terminal-line${className ? ` ${className}` : ""}`;
    line.textContent = text || "\u00a0";
    output.appendChild(line);

    while (output.children.length > 120) {
      output.removeChild(output.firstElementChild);
    }

    output.scrollTop = output.scrollHeight;
  };

  const appendPrompt = (command) => {
    appendLine(`${prompt} ${command}`, "terminal-line-command");
  };

  const appendBlock = (lines, className = "") => {
    lines.forEach((line) => appendLine(line, className));
  };

  const scrollToSection = (selector) => {
    const section = document.querySelector(selector);
    if (!section) return;
    section.scrollIntoView({ behavior: "smooth", block: "start" });
  };

  const commandHandlers = {
    help: () => [
      "Dostepne komendy:",
      "help       pokazuje liste komend",
      "projects   przechodzi do projektow",
      "stack      pokazuje technologie",
      "status     pokazuje status buildow",
      "contact    pokazuje adres kontaktowy",
      "github     pokazuje adres repozytoriow",
      "clear      czysci terminal",
      "date       pokazuje date przegladarki",
      "echo tekst wypisuje tekst",
    ],
    projects: () => {
      scrollToSection("#projects");
      return [
        "Otwieram sekcje projektow...",
        "PunisherX     Minecraft Plugin",
        "SyntaxCore    Core Library",
        "Econizer       Discord Bot",
        "MedStock      Android APK",
      ];
    },
    projekty: () => commandHandlers.projects(),
    stack: () => {
      scrollToSection("#stack");
      return [
        "Stack:",
        "Minecraft: Paper, Folia, Kotlin, Adventure, Maven",
        "Discord: boty, OAuth, dashboard WWW",
        "Android: APK, synchronizacja danych, UX mobile",
      ];
    },
    status: () => [
      "system: online",
      "build-channel: ready",
      "deploy-window: open",
      "latency: local",
    ],
    contact: () => {
      const email = terminal.dataset.contactEmail || "contact@syntaxdevteam.pl";
      return [`Kontakt: ${email}`];
    },
    kontakt: () => commandHandlers.contact(),
    github: () => {
      const url = terminal.dataset.githubUrl || "#";
      return url === "#" ? ["Repozytoria: link nie jest jeszcze skonfigurowany"] : [`Repozytoria: ${url}`];
    },
    repo: () => commandHandlers.github(),
    clear: () => {
      if (output) output.innerHTML = "";
      return [];
    },
    date: () => [new Date().toLocaleString("pl-PL")],
    whoami: () => ["guest@sdt-cli"],
  };

  const runCommand = (rawCommand) => {
    const command = rawCommand.trim();
    appendPrompt(command);

    if (command === "") return;

    const [name, ...args] = command.split(/\s+/);
    const normalized = name.toLowerCase();

    if (normalized === "echo") {
      appendLine(args.join(" "));
      return;
    }

    const handler = commandHandlers[normalized];
    if (!handler) {
      appendLine(`Nieznana komenda: ${name}. Wpisz 'help'.`, "terminal-line-error");
      return;
    }

    appendBlock(handler());
  };

  terminal.addEventListener("click", () => {
    input?.focus();
  });

  form?.addEventListener("submit", (event) => {
    event.preventDefault();

    if (!input) return;

    const command = input.value;
    if (command.trim() !== "") {
      history.push(command);
      historyIndex = history.length;
    }

    input.value = "";
    runCommand(command);
  });

  input?.addEventListener("keydown", (event) => {
    if (event.key === "ArrowUp") {
      event.preventDefault();
      if (historyIndex > 0) historyIndex -= 1;
      input.value = history[historyIndex] || "";
      input.setSelectionRange(input.value.length, input.value.length);
    }

    if (event.key === "ArrowDown") {
      event.preventDefault();
      if (historyIndex < history.length) historyIndex += 1;
      input.value = history[historyIndex] || "";
      input.setSelectionRange(input.value.length, input.value.length);
    }

    if (event.key === "Tab") {
      const current = input.value.trim().toLowerCase();
      const match = commandNames.find((commandName) => commandName.startsWith(current));
      if (match) {
        event.preventDefault();
        input.value = match;
      }
    }

    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === "l") {
      event.preventDefault();
      if (output) output.innerHTML = "";
    }
  });
}
