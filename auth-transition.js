const loginForm = document.querySelector('form[data-login-form]');

if (loginForm instanceof HTMLFormElement) {
    const submitButton = loginForm.querySelector('button[type="submit"]');
    const status = document.querySelector('[data-login-status]');

    loginForm.addEventListener('submit', (event) => {
        if (!(submitButton instanceof HTMLButtonElement) || !(status instanceof HTMLElement)) {
            return;
        }

        event.preventDefault();
        submitButton.disabled = true;
        submitButton.setAttribute('aria-busy', 'true');
        submitButton.textContent = submitButton.dataset.loadingLabel || submitButton.textContent;
        status.hidden = false;

        const spinner = document.createElement('span');
        spinner.className = 'auth-transition-spinner';
        spinner.setAttribute('aria-hidden', 'true');
        status.replaceChildren(spinner, document.createTextNode(status.dataset.loadingLabel || ''));

        window.setTimeout(() => loginForm.submit(), 1200);
    });
}

const logoutPage = document.querySelector('[data-logout-page]');
if (logoutPage instanceof HTMLElement && logoutPage.dataset.redirectUrl) {
    window.setTimeout(() => {
        window.location.replace(logoutPage.dataset.redirectUrl);
    }, 2000);
}
