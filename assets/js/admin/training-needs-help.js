
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('tna-help-modal');
    const openButton = document.querySelector('[data-tna-help-open]');

    if (!modal || !openButton) return;

    const closeButtons = modal.querySelectorAll('[data-tna-help-close]');
    let previousFocus = null;

    function openModal() {
        previousFocus = document.activeElement;

        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';

        modal.querySelector('.tna-modal-close')?.focus();
    }

    function closeModal() {
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';

        if (previousFocus instanceof HTMLElement) {
            previousFocus.focus();
        }
    }

    openButton.addEventListener('click', openModal);

    closeButtons.forEach(button => {
        button.addEventListener('click', closeModal);
    });

    document.addEventListener('keydown', event => {
        if (
            event.key === 'Escape' &&
            modal.getAttribute('aria-hidden') === 'false'
        ) {
            closeModal();
        }
    });
});
