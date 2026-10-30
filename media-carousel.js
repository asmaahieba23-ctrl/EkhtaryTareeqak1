(() => {
    document.querySelectorAll('.content-carousel').forEach((carousel) => {
        const slides = Array.from(carousel.querySelectorAll('.content-carousel-slide'));
        const count = carousel.querySelector('.content-carousel-count');
        if (slides.length < 2) {
            return;
        }

        let activeIndex = slides.findIndex((slide) => slide.classList.contains('is-active'));
        if (activeIndex < 0) {
            activeIndex = 0;
        }

        function showSlide(nextIndex) {
            activeIndex = (nextIndex + slides.length) % slides.length;
            slides.forEach((slide, index) => {
                const isActive = index === activeIndex;
                slide.hidden = !isActive;
                slide.classList.toggle('is-active', isActive);
                slide.setAttribute('aria-hidden', isActive ? 'false' : 'true');
                if (!isActive) {
                    slide.querySelectorAll('video').forEach((video) => video.pause());
                }
            });

            if (count) {
                count.textContent = `${activeIndex + 1} / ${slides.length}`;
            }
        }

        carousel.querySelectorAll('[data-carousel-direction]').forEach((button) => {
            button.addEventListener('click', () => {
                const direction = Number(button.dataset.carouselDirection);
                showSlide(activeIndex + direction);
            });
        });

        showSlide(activeIndex);
    });
})();
