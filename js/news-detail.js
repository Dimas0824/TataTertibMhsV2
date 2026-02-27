document.addEventListener("DOMContentLoaded", () => {
    const carousel = document.querySelector("[data-news-carousel]");
    const track = carousel ? carousel.querySelector("[data-carousel-track]") : null;
    const prevButton = document.querySelector("[data-carousel-prev]");
    const nextButton = document.querySelector("[data-carousel-next]");

    if (!carousel || !track || !prevButton || !nextButton) {
        return;
    }

    const getStep = () => {
        const firstCard = track.querySelector(".related-news-card");
        const trackStyles = window.getComputedStyle(track);
        const columnGap = parseFloat(trackStyles.columnGap || trackStyles.gap || "0");

        if (!firstCard) {
            return Math.max(track.clientWidth * 0.75, 260);
        }

        const cardWidth = firstCard.getBoundingClientRect().width;
        return cardWidth + columnGap;
    };

    const updateButtons = () => {
        const maxScroll = track.scrollWidth - track.clientWidth;
        const current = Math.ceil(track.scrollLeft);

        prevButton.disabled = current <= 0;
        nextButton.disabled = current >= Math.floor(maxScroll);
    };

    prevButton.addEventListener("click", () => {
        track.scrollBy({ left: -getStep(), behavior: "smooth" });
    });

    nextButton.addEventListener("click", () => {
        track.scrollBy({ left: getStep(), behavior: "smooth" });
    });

    track.addEventListener("scroll", updateButtons, { passive: true });
    window.addEventListener("resize", updateButtons);
    updateButtons();
});
