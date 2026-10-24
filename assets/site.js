/**
 * Site chrome controller: the floating header and the horizontal scrollers.
 *
 * Both are progressive enhancement. The header is legible without this file
 * (its default state is the solid one) and the scrollers are natively
 * scrollable and keyboard operable; this only adds the polish.
 */
(() => {
    "use strict";

    const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)");

    /* ---- Floating header ----
       Transparent over the top of the page, then solid with a hairline once the
       page has scrolled past the first slice of the fold. The classes are added
       to the element the template already renders, so no markup is generated
       here. */
    const header = document.getElementById("site-header");
    if (header) {
        const SOLID = ["border-brand-ink/10", "bg-surface/90", "backdrop-blur-md"];
        const CLEAR = ["border-transparent", "bg-surface/0"];

        const sync = () => {
            const scrolled = window.scrollY > 16;
            header.classList.remove(...(scrolled ? CLEAR : SOLID));
            header.classList.add(...(scrolled ? SOLID : CLEAR));
        };

        sync();
        window.addEventListener("scroll", sync, { passive: true });
    }

    /* ---- Horizontal scrollers ----
       One step per click, measured from the first card so the step follows the
       responsive card width rather than a hard-coded pixel value. Wraps at each
       end so the controls never become dead. */
    document.querySelectorAll("[data-scroller]").forEach((track) => {
        const root = track.closest("section") || document;

        const step = () => {
            const first = track.firstElementChild;
            if (!first) return 320;

            const styles = window.getComputedStyle(track);
            const gap = parseFloat(styles.columnGap || styles.gap) || 0;

            return first.getBoundingClientRect().width + gap;
        };

        const goTo = (left) => {
            track.scrollTo({
                left,
                behavior: prefersReducedMotion.matches ? "auto" : "smooth",
            });
        };

        const maxScroll = () => track.scrollWidth - track.clientWidth;

        root.querySelector("[data-scroller-prev]")?.addEventListener("click", () => {
            const target = track.scrollLeft - step();

            goTo(target < 2 ? maxScroll() : target);
        });

        root.querySelector("[data-scroller-next]")?.addEventListener("click", () => {
            const target = track.scrollLeft + step();

            goTo(target > maxScroll() - 2 ? 0 : target);
        });

        /* A vertical wheel over a horizontal track scrolls it sideways, but
           only when the track actually overflows and the gesture is mostly
           vertical: otherwise the page would stop scrolling normally. */
        track.addEventListener(
            "wheel",
            (event) => {
                if (track.scrollWidth <= track.clientWidth) return;
                if (Math.abs(event.deltaX) > Math.abs(event.deltaY)) return;

                event.preventDefault();
                track.scrollLeft += event.deltaY;
            },
            { passive: false },
        );
    });
})();
