document.querySelectorAll('[data-password-toggle]').forEach((toggle) => {
    const inputId = toggle.getAttribute('aria-controls');
    const passwordInput = inputId ? document.getElementById(inputId) : null;

    if (!(passwordInput instanceof HTMLInputElement)) {
        return;
    }

    toggle.addEventListener('click', () => {
        const shouldShow = passwordInput.type === 'password';
        passwordInput.type = shouldShow ? 'text' : 'password';
        toggle.setAttribute('aria-pressed', String(shouldShow));
        toggle.setAttribute(
            'aria-label',
            shouldShow ? toggle.dataset.hideLabel : toggle.dataset.showLabel
        );
    });
});
