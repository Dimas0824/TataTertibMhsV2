document.addEventListener("DOMContentLoaded", () => {
    const revealElements = document.querySelectorAll(".reveal-up");

    if ("IntersectionObserver" in window) {
        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        const delay = entry.target.getAttribute("data-delay");
                        if (delay) {
                            entry.target.style.setProperty("--reveal-delay", `${delay}ms`);
                        }
                        entry.target.classList.add("in-view");
                        observer.unobserve(entry.target);
                    }
                });
            },
            {
                threshold: 0.15,
                rootMargin: "0px 0px -40px 0px"
            }
        );

        revealElements.forEach((element) => observer.observe(element));
    } else {
        revealElements.forEach((element) => element.classList.add("in-view"));
    }
});
