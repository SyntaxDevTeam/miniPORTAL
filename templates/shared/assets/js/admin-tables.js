(() => {
  const collator = new Intl.Collator("pl-PL", {
    numeric: true,
    sensitivity: "base",
  });
  const actionLabels = new Set(["akcja", "akcje", "action", "actions"]);
  const sizeUnits = {
    b: 1,
    kb: 1000,
    mb: 1000 ** 2,
    gb: 1000 ** 3,
    tb: 1000 ** 4,
    kib: 1024,
    mib: 1024 ** 2,
    gib: 1024 ** 3,
    tib: 1024 ** 4,
  };

  const normalizedText = (value) => value
    .replaceAll("\u00a0", " ")
    .replace(/\s+/g, " ")
    .trim();

  const localizedNumber = (value) => Number(
    value.replace(/\s+/g, "").replace(",", ".")
  );

  const sortableValue = (cell) => {
    const text = normalizedText(cell.dataset.sortValue ?? cell.textContent ?? "");
    if (text === "" || text === "—" || text === "-") {
      return { type: "empty", value: "" };
    }

    const size = text.match(/^([-+]?\d+(?:[.,]\d+)?)\s*(B|KB|MB|GB|TB|KiB|MiB|GiB|TiB)\b/i);
    if (size) {
      return {
        type: "number",
        value: localizedNumber(size[1]) * sizeUnits[size[2].toLocaleLowerCase("en-US")],
      };
    }

    if (/^[-+]?\d+(?:[.,]\d+)?\s*%?$/.test(text)) {
      return {
        type: "number",
        value: localizedNumber(text.replace("%", "")),
      };
    }

    const isoDate = text.match(
      /^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?$/
    );
    if (isoDate) {
      return {
        type: "date",
        value: Date.UTC(
          Number(isoDate[1]),
          Number(isoDate[2]) - 1,
          Number(isoDate[3]),
          Number(isoDate[4] || 0),
          Number(isoDate[5] || 0),
          Number(isoDate[6] || 0)
        ),
      };
    }

    const polishDate = text.match(
      /^(\d{2})[./-](\d{2})[./-](\d{4})(?:\s+(\d{2}):(\d{2})(?::(\d{2}))?)?$/
    );
    if (polishDate) {
      return {
        type: "date",
        value: Date.UTC(
          Number(polishDate[3]),
          Number(polishDate[2]) - 1,
          Number(polishDate[1]),
          Number(polishDate[4] || 0),
          Number(polishDate[5] || 0),
          Number(polishDate[6] || 0)
        ),
      };
    }

    return { type: "text", value: text };
  };

  const compareValues = (left, right) => {
    if (left.type === "empty" && right.type === "empty") return 0;
    if (left.type === "empty") return 1;
    if (right.type === "empty") return -1;
    if (left.type === right.type && ["number", "date"].includes(left.type)) {
      return left.value - right.value;
    }

    return collator.compare(String(left.value), String(right.value));
  };

  const hasActionControls = (cells) => cells.some((cell) => cell?.querySelector(
    "form, button, .btn, .admin-table-actions, .translation-table-actions"
  ));

  document.querySelectorAll("body.admin-stylebook table").forEach((table) => {
    if (
      table.dataset.sortable === "false"
      || table.classList.contains("admin-data-table-single")
      || !table.tHead
      || table.tBodies.length === 0
      || table.querySelector(
        "thead .admin-table-sort, thead .database-history-sort, thead a[href*='sort=']"
      )
    ) {
      return;
    }

    const headerRow = table.tHead.rows[0];
    const bodies = [...table.tBodies];
    const rows = bodies.flatMap((body) => [...body.rows]);
    if (!headerRow || rows.length === 0) {
      return;
    }

    const status = document.createElement("span");
    status.className = "visually-hidden";
    status.setAttribute("role", "status");
    status.setAttribute("aria-live", "polite");
    table.insertAdjacentElement("afterend", status);

    [...headerRow.cells].forEach((header, columnIndex) => {
      const label = normalizedText(header.textContent ?? "");
      const cells = rows
        .filter((row) => row.cells.length === headerRow.cells.length)
        .map((row) => row.cells[columnIndex]);

      if (
        header.dataset.sortable === "false"
        || header.colSpan !== 1
        || header.querySelector("a, button, input, select")
        || actionLabels.has(label.toLocaleLowerCase("pl-PL"))
        || cells.length === 0
        || hasActionControls(cells)
      ) {
        return;
      }

      const button = document.createElement("button");
      const indicator = document.createElement("span");
      const headerContent = [...header.childNodes];
      button.type = "button";
      button.className = "admin-table-sort admin-table-sort-client";
      button.setAttribute("aria-label", `Sortuj kolumnę ${label}`);
      button.title = `Sortuj widoczne wiersze według kolumny „${label}”`;
      indicator.className = "admin-table-sort-indicator";
      indicator.setAttribute("aria-hidden", "true");
      indicator.textContent = "↕";
      button.append(...headerContent, indicator);
      header.append(button);
      header.setAttribute("aria-sort", "none");

      button.addEventListener("click", () => {
        const direction = header.getAttribute("aria-sort") === "ascending"
          ? "descending"
          : "ascending";

        headerRow.querySelectorAll("[aria-sort]").forEach((otherHeader) => {
          otherHeader.setAttribute("aria-sort", "none");
          const otherButton = otherHeader.querySelector(".admin-table-sort-client");
          if (otherButton) {
            otherButton.classList.remove("is-active");
            const otherIndicator = otherButton.querySelector(".admin-table-sort-indicator");
            if (otherIndicator) otherIndicator.textContent = "↕";
          }
        });

        header.setAttribute("aria-sort", direction);
        button.classList.add("is-active");
        indicator.textContent = direction === "ascending" ? "↑" : "↓";

        bodies.forEach((body) => {
          const bodyRows = [...body.rows];
          const sortableRows = bodyRows
            .map((row, originalIndex) => ({
              row,
              originalIndex,
              cell: row.cells.length === headerRow.cells.length
                ? row.cells[columnIndex]
                : null,
            }))
            .filter((entry) => entry.cell !== null);
          const remainingRows = bodyRows.filter(
            (row) => row.cells.length !== headerRow.cells.length
          );

          sortableRows.sort((left, right) => {
            const leftValue = sortableValue(left.cell);
            const rightValue = sortableValue(right.cell);
            const comparison = compareValues(leftValue, rightValue);
            const keepEmptyLast = leftValue.type === "empty" || rightValue.type === "empty";
            const directed = direction === "ascending" || keepEmptyLast
              ? comparison
              : -comparison;
            return directed || left.originalIndex - right.originalIndex;
          });
          body.append(...sortableRows.map((entry) => entry.row), ...remainingRows);
        });

        status.textContent = `Posortowano według kolumny ${label}, ${
          direction === "ascending" ? "rosnąco" : "malejąco"
        }.`;
      });
    });

    table.dataset.adminSortable = "true";
  });
})();
