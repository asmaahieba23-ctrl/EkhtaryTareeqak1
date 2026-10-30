(() => {
    const backdrop = document.getElementById('assistant-backdrop');
    const dialog = document.getElementById('assistant-dialog');
    const form = document.getElementById('assistant-form');
    const input = document.getElementById('assistant-input');
    const messages = document.getElementById('assistant-messages');
    const sendButton = form?.querySelector('button[type="submit"]');
    const launcher = document.querySelector('.assistant-launcher');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    if (!backdrop || !dialog || !form || !input || !messages || !sendButton || !launcher || !csrfToken) {
        return;
    }

    const endpoint = dialog.dataset.endpoint;
    let previousFocus = null;

    function openAssistant() {
        previousFocus = document.activeElement;
        backdrop.hidden = false;
        document.body.classList.add('assistant-open');
        input.focus();
    }

    function closeAssistant() {
        backdrop.hidden = true;
        document.body.classList.remove('assistant-open');
        if (previousFocus instanceof HTMLElement) {
            previousFocus.focus();
        }
    }

    function addMessage(text, sender) {
        const message = document.createElement('div');
        message.className = `assistant-message ${sender}`;
        message.textContent = text;
        messages.appendChild(message);
        messages.scrollTop = messages.scrollHeight;
    }

    async function sendMessage(value) {
        const text = value.trim();
        if (!text || sendButton.disabled) {
            return;
        }

        addMessage(text, 'assistant-user-message');
        input.value = '';
        sendButton.disabled = true;
        const typing = document.createElement('div');
        typing.className = 'assistant-message assistant-bot-message assistant-typing';
        typing.setAttribute('role', 'status');
        typing.setAttribute('aria-label', dialog.dataset.typing);
        typing.innerHTML = '<span></span><span></span><span></span>';
        messages.appendChild(typing);
        messages.scrollTop = messages.scrollHeight;

        const startedAt = Date.now();
        let reply;
        try {
            const formData = new FormData();
            formData.append('message', text);

            const response = await fetch(endpoint, {
                method: 'POST',
                body: formData,
                headers: {'X-CSRF-Token': csrfToken}
            });
            const data = await response.json();

            if (!response.ok) {
                throw new Error(dialog.dataset.sendError);
            }

            reply = data.reply || dialog.dataset.retry;
        } catch (error) {
            reply = dialog.dataset.connectionError;
        } finally {
            const remainingTypingTime = Math.max(0, 2200 - (Date.now() - startedAt));
            await new Promise((resolve) => setTimeout(resolve, remainingTypingTime));
            typing.remove();
            addMessage(reply, 'assistant-bot-message');
            sendButton.disabled = false;
            input.focus();
        }
    }

    document.querySelectorAll('[data-open-assistant]').forEach((trigger) => {
        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            openAssistant();
        });
    });

    backdrop.addEventListener('click', (event) => {
        if (event.target === backdrop || event.target.closest('[data-close-assistant]')) {
            closeAssistant();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !backdrop.hidden) {
            closeAssistant();
        }

        if (event.key === 'Tab' && !backdrop.hidden) {
            const focusable = dialog.querySelectorAll(
                'button:not(:disabled), input:not(:disabled), a[href], [tabindex]:not([tabindex="-1"])'
            );
            const first = focusable[0];
            const last = focusable[focusable.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }
    });

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        sendMessage(input.value);
    });

    dialog.querySelectorAll('[data-assistant-question]').forEach((button) => {
        button.addEventListener('click', () => {
            sendMessage(button.dataset.assistantQuestion || '');
        });
    });
})();
