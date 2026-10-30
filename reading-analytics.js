(() => {
    const container = document.querySelector('[data-reading-analytics]');
    if (!container) {
        return;
    }

    const contentType = container.dataset.contentType;
    const endpoint = container.dataset.endpoint;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    const cards = Array.from(container.querySelectorAll('[data-reading-content]'));
    const trackedActions = new Set();
    const visibleSince = new Map();
    const viewedContent = new Set();

    if (!contentType || !endpoint || !csrfToken || cards.length === 0) {
        return;
    }

    function sendTrackingUpdate(card, action) {
        const contentId = card.dataset.readingContent;
        const key = `${contentType}:${contentId}:${action}`;
        if (!contentId || trackedActions.has(key)) {
            return;
        }

        trackedActions.add(key);
        fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({
                content_type: contentType,
                content_id: contentId,
                action
            })
        }).catch((error) => {
            trackedActions.delete(key);
            console.error('Reading analytics error:', error);
        });
    }

    function updateVisibleTime() {
        if (document.hidden) {
            visibleSince.clear();
            return;
        }

        const now = Date.now();
        for (const card of cards) {
            if (!viewedContent.has(card)) {
                continue;
            }
            if (!visibleSince.has(card)) {
                visibleSince.set(card, now);
            }
            if (now - visibleSince.get(card) >= 30000) {
                sendTrackingUpdate(card, 'stayed_30_sec');
            }
        }
    }

    const observer = new IntersectionObserver((entries) => {
        for (const entry of entries) {
            if (entry.isIntersecting && entry.intersectionRatio >= 0.25) {
                viewedContent.add(entry.target);
                visibleSince.set(entry.target, Date.now());
            } else {
                visibleSince.delete(entry.target);
            }
        }
    }, {threshold: [0, 0.25]});

    cards.forEach((card) => observer.observe(card));

    const dwellTimer = window.setInterval(updateVisibleTime, 1000);
    document.addEventListener('visibilitychange', updateVisibleTime);
    window.addEventListener('pagehide', () => {
        window.clearInterval(dwellTimer);
        observer.disconnect();
    }, {once: true});

    let scrollTriggered = false;
    window.addEventListener('scroll', () => {
        if (scrollTriggered) {
            return;
        }

        const pageBottom = window.innerHeight + window.scrollY;
        if (pageBottom >= document.documentElement.scrollHeight * 0.9) {
            scrollTriggered = true;
            viewedContent.forEach((card) => sendTrackingUpdate(card, 'scrolled_to_end'));
        }
    }, {passive: true});
})();
