document.addEventListener('DOMContentLoaded', () => {
    const wrappers = document.querySelectorAll(
        '.table-wrap[data-max-rows], ' +
        '.employee-table-scroll[data-max-rows], ' +
        '.competency-table-scroll[data-max-rows], ' +
        '.attendance-table-wrap[data-max-rows], ' +
        '.certificates-table-wrap[data-max-rows]'
    );

    wrappers.forEach((wrapper) => {
        const table = wrapper.querySelector('table');
        const tbody = table?.querySelector('tbody');
        const thead = table?.querySelector('thead');

        if (!table || !tbody) return;

        const rowLimit = Number(wrapper.dataset.maxRows);

        if (!Number.isInteger(rowLimit) || rowLimit < 1) return;

        function adjustHeight() {
            const rows = Array.from(tbody.querySelectorAll('tr'))
                .filter(row => !row.hidden);

            wrapper.style.overflowX = 'auto';

            if (rows.length <= rowLimit) {
                wrapper.style.maxHeight = 'none';
                wrapper.style.overflowY = 'visible';
                return;
            }

            const headerHeight = thead
                ? thead.getBoundingClientRect().height
                : 0;

            const rowsHeight = rows
                .slice(0, rowLimit)
                .reduce((total, row) => {
                    return total + row.getBoundingClientRect().height;
                }, 0);

            wrapper.style.maxHeight =
                `${Math.ceil(headerHeight + rowsHeight + 2)}px`;

            wrapper.style.overflowY = 'auto';
        }

        const resizeObserver = new ResizeObserver(adjustHeight);
        resizeObserver.observe(table);

        if (thead) {
            resizeObserver.observe(thead);
        }

        const mutationObserver = new MutationObserver(adjustHeight);

        mutationObserver.observe(tbody, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['hidden']
        });

        window.addEventListener('resize', adjustHeight);

        requestAnimationFrame(adjustHeight);
    });
});