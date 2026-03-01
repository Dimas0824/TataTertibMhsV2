(function () {
    const normalize = (value) => String(value || "").trim().replace(/\s+/g, " ").toLowerCase();

    const tables = Array.from(document.querySelectorAll("[data-universal-table]"));
    if (tables.length === 0) {
        return;
    }

    tables.forEach((table) => {
        if (!(table instanceof HTMLTableElement)) {
            return;
        }

        const tableId = table.getAttribute("id");
        if (!tableId) {
            return;
        }

        const tools = document.querySelector(`[data-table-tools][data-table-target="${tableId}"]`);
        if (!tools) {
            return;
        }

        const tbody = table.querySelector("[data-table-body]");
        if (!(tbody instanceof HTMLTableSectionElement)) {
            return;
        }

        const rows = Array.from(tbody.querySelectorAll("[data-table-row]"));
        if (rows.length === 0) {
            return;
        }

        const searchInput = tools.querySelector("[data-table-search]");
        const filterInputs = Array.from(tools.querySelectorAll("select[data-table-filter-key]"));
        const tabButtons = Array.from(
            tools.querySelectorAll("button[data-table-tab-key][data-table-tab-value]")
        );
        const visibleCountElement = tools.querySelector("[data-table-visible-count]");

        const buildNoResultRow = () => {
            const colCount = Math.max(1, table.querySelectorAll("thead th").length);
            const tr = document.createElement("tr");
            tr.className = "table-filter-empty-row";
            tr.setAttribute("data-table-empty-dynamic", "true");

            const td = document.createElement("td");
            td.className = "empty-cell";
            td.colSpan = colCount;
            td.textContent = "Tidak ada data yang cocok dengan filter saat ini.";
            tr.appendChild(td);
            return tr;
        };

        let dynamicEmptyRow = null;

        const applyFilter = () => {
            const searchTerm = searchInput instanceof HTMLInputElement ? normalize(searchInput.value) : "";
            const activeTabFilters = new Map();

            tabButtons.forEach((button) => {
                if (!(button instanceof HTMLButtonElement)) {
                    return;
                }

                if (button.getAttribute("aria-pressed") !== "true") {
                    return;
                }

                const filterKey = normalize(button.getAttribute("data-table-tab-key"));
                const filterValue = normalize(button.getAttribute("data-table-tab-value"));
                if (filterKey !== "" && filterValue !== "") {
                    activeTabFilters.set(filterKey, filterValue);
                }
            });

            let visibleCount = 0;

            rows.forEach((row) => {
                if (!(row instanceof HTMLTableRowElement)) {
                    return;
                }

                const searchText = normalize(row.getAttribute("data-table-search") || "");
                const searchMatched = searchTerm === "" || searchText.includes(searchTerm);

                const selectFiltersMatched = filterInputs.every((filterInput) => {
                    if (!(filterInput instanceof HTMLSelectElement)) {
                        return true;
                    }

                    const filterKey = normalize(filterInput.getAttribute("data-table-filter-key"));
                    if (filterKey === "") {
                        return true;
                    }

                    const expectedValue = normalize(filterInput.value);
                    if (expectedValue === "") {
                        return true;
                    }

                    const rowValue = normalize(row.getAttribute(`data-table-filter-${filterKey}`) || "");
                    return rowValue === expectedValue;
                });

                let tabFiltersMatched = true;
                activeTabFilters.forEach((expectedValue, filterKey) => {
                    if (!tabFiltersMatched) {
                        return;
                    }

                    const rowValue = normalize(row.getAttribute(`data-table-filter-${filterKey}`) || "");
                    if (rowValue !== expectedValue) {
                        tabFiltersMatched = false;
                    }
                });

                const isVisible = searchMatched && selectFiltersMatched && tabFiltersMatched;
                row.hidden = !isVisible;
                if (isVisible) {
                    visibleCount += 1;
                }
            });

            if (visibleCountElement) {
                visibleCountElement.textContent = String(visibleCount);
            }

            if (visibleCount === 0) {
                if (!dynamicEmptyRow) {
                    dynamicEmptyRow = buildNoResultRow();
                }
                if (!tbody.querySelector("[data-table-empty-dynamic]")) {
                    tbody.appendChild(dynamicEmptyRow);
                }
            } else if (dynamicEmptyRow && dynamicEmptyRow.parentElement === tbody) {
                tbody.removeChild(dynamicEmptyRow);
            }
        };

        if (searchInput instanceof HTMLInputElement) {
            searchInput.addEventListener("input", applyFilter);
        }

        filterInputs.forEach((filterInput) => {
            filterInput.addEventListener("change", applyFilter);
        });

        tabButtons.forEach((button) => {
            button.addEventListener("click", () => {
                if (!(button instanceof HTMLButtonElement)) {
                    return;
                }

                const currentKey = normalize(button.getAttribute("data-table-tab-key"));
                if (currentKey === "") {
                    return;
                }

                tabButtons.forEach((candidate) => {
                    if (!(candidate instanceof HTMLButtonElement)) {
                        return;
                    }

                    const candidateKey = normalize(candidate.getAttribute("data-table-tab-key"));
                    if (candidateKey !== currentKey) {
                        return;
                    }

                    const isActive = candidate === button;
                    candidate.setAttribute("aria-pressed", isActive ? "true" : "false");
                    candidate.classList.toggle("is-active", isActive);
                });

                applyFilter();
            });
        });

        applyFilter();
    });
})();
