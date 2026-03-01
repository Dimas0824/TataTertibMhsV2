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
        const filterInputs = Array.from(tools.querySelectorAll("[data-table-filter-key]"));
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
            let visibleCount = 0;

            rows.forEach((row) => {
                if (!(row instanceof HTMLTableRowElement)) {
                    return;
                }

                const searchText = normalize(row.getAttribute("data-table-search") || "");
                const searchMatched = searchTerm === "" || searchText.includes(searchTerm);

                const filtersMatched = filterInputs.every((filterInput) => {
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

                const isVisible = searchMatched && filtersMatched;
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

        applyFilter();
    });
})();
