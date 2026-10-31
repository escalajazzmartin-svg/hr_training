document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('.tna-auto-filters');

    const searchInput = form?.querySelector('[name="q"]');
    const departmentSelect = form?.querySelector('[name="department"]');
    const prioritySelect = form?.querySelector('[name="priority"]');
    const tableCard = form?.closest('.tna-table-card');
    const searchToggle = document.getElementById('tna-search-toggle');
    const filtersToggle = document.getElementById('tna-filters-toggle');

    const tableContainer = document.querySelector('.tna-table-results');
    const resultCount = document.querySelector('.tna-result-count');

    if (!form || !tableContainer || !resultCount) {
        return;
    }

    searchToggle?.addEventListener('click', () => {
        const isOpen = tableCard?.classList.toggle('search-open') || false;
        searchToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');

        if (isOpen) {
            searchInput?.focus();
        }
    });

    filtersToggle?.addEventListener('click', () => {
        const isOpen = tableCard?.classList.toggle('filters-open') || false;
        filtersToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });

    const table = tableContainer.querySelector('table');
    const tbody = table?.querySelector('tbody');

    if (!tbody) {
        return;
    }

    const allRows = Array.from(tbody.querySelectorAll('tr'));

    const dataRows = allRows.filter(
        row => row.querySelectorAll('td').length > 1
    );

    const originalEmptyRow = allRows.find(
        row => row.querySelectorAll('td').length <= 1
    );

    let dynamicEmptyRow = null;

    function ensureEmptyRow() {
        if (dynamicEmptyRow) {
            return dynamicEmptyRow;
        }

        dynamicEmptyRow = document.createElement('tr');
        dynamicEmptyRow.className = 'tna-no-matches';

        const cell = document.createElement('td');

        cell.colSpan = table.querySelectorAll('thead th').length || 7;
        cell.textContent =
            'No competency gaps match your current search or filters.';

        dynamicEmptyRow.appendChild(cell);
        tbody.appendChild(dynamicEmptyRow);

        return dynamicEmptyRow;
    }

    function filterRows() {
        const query = (searchInput?.value || '')
            .trim()
            .toLocaleLowerCase();

        const department = departmentSelect?.value || '';
        const priority = prioritySelect?.value || '';

        let visibleCount = 0;

        dataRows.forEach(row => {
            const cells = row.querySelectorAll('td');

            const rowText = row.textContent.toLocaleLowerCase();
            const rowDepartment = (cells[1]?.textContent || '').trim();

            const gapBadge = row.querySelector('[data-gap]');
            const gap = Number(gapBadge?.dataset.gap || 0);

            const matchesSearch =
                !query || rowText.includes(query);

            const matchesDepartment =
                !department || rowDepartment === department;

            const matchesPriority =
                !priority ||
                (priority === 'high' && gap >= 20) ||
                (priority === 'standard' && gap < 20);

            const visible =
                matchesSearch &&
                matchesDepartment &&
                matchesPriority;

            row.hidden = !visible;

            if (visible) {
                visibleCount++;
            }
        });

        if (originalEmptyRow) {
            originalEmptyRow.hidden = true;
        }

        const emptyRow = ensureEmptyRow();
        emptyRow.hidden = visibleCount !== 0;

        resultCount.textContent =
            `${visibleCount} gap record(s) shown out of ` +
            `${dataRows.length} identified gap record(s).`;
    }

    const clearButton = form.querySelector('.tna-clear-button');

    clearButton?.addEventListener('click', () => {
        if (searchInput) searchInput.value = '';
        if (departmentSelect) departmentSelect.value = '';
        if (prioritySelect) prioritySelect.value = '';

        filterRows();
    });

    // Prevent form submission from refreshing the page.
    form.addEventListener('submit', event => {
        event.preventDefault();
    });

    // Search updates while the user types.
    searchInput?.addEventListener('input', filterRows);

    // Dropdown filters update immediately when changed.
    departmentSelect?.addEventListener('change', filterRows);
    prioritySelect?.addEventListener('change', filterRows);

    // Display the initial record count.
    filterRows();
});