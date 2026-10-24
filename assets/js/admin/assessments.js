
document.addEventListener('DOMContentLoaded', () => {
    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-assessment-toggle]');

        if (!trigger) return;

        const detailsId = trigger.dataset.assessmentToggle;
        const detailsRow = document.getElementById(detailsId);

        if (!detailsRow) return;

        const isOpening = detailsRow.hidden;

        // Close any other expanded assessment row in this module.
        const page = trigger.closest('.assessment-page');

        if (page) {
            page.querySelectorAll('.assessment-details-row').forEach((row) => {
                if (row.id !== detailsId) {
                    row.hidden = true;

                    page.querySelectorAll(
                        `[data-assessment-toggle="${row.id}"]`
                    ).forEach((button) => {
                        button.setAttribute('aria-expanded', 'false');
                    });
                }
            });
        }

        detailsRow.hidden = !isOpening;

        if (page) {
            page.querySelectorAll(
                `[data-assessment-toggle="${detailsId}"]`
            ).forEach((button) => {
                button.setAttribute(
                    'aria-expanded',
                    String(isOpening)
                );
            });
        }

        if (isOpening) {
            const scoreInput = detailsRow.querySelector(
                'input[name="score"]'
            );

            scoreInput?.focus({ preventScroll: true });
        }
    });
});
