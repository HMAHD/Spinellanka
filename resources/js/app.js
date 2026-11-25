const isPopoverOpen = (element) =>
  element.matches(":is(:popover-open, .\\:popover-open)");

const loadPopoverFallback = async () => {
  if (!("popover" in HTMLElement.prototype)) {
    await import("@oddbird/popover-polyfill");
  }
};

const setupResponsiveMenu = () => {
  const menu = document.querySelector("#site-menu");
  const mobile = window.matchMedia("(max-width: 51.99rem)");

  if (!(menu instanceof HTMLElement)) {
    return;
  }

  const sync = () => {
    if (mobile.matches) {
      menu.setAttribute("popover", "auto");
      return;
    }

    if (isPopoverOpen(menu)) {
      menu.hidePopover();
    }

    menu.removeAttribute("popover");
  };

  mobile.addEventListener("change", sync);
  sync();
};

const closeMenuAfterNavigation = () => {
  const menu = document.querySelector("#site-menu");

  if (!(menu instanceof HTMLElement)) {
    return;
  }

  const close = () => {
    if (isPopoverOpen(menu)) {
      menu.hidePopover();
    }
  };

  menu.addEventListener("click", (event) => {
    const anchor =
      event.target instanceof Element ? event.target.closest("a") : null;

    if (!anchor) {
      return;
    }

    close();
  });

  window.addEventListener("scroll", close, { passive: true });
};

const revealSections = () => {
  if (
    !("IntersectionObserver" in window) ||
    window.matchMedia("(prefers-reduced-motion: reduce)").matches
  ) {
    return;
  }

  const elements = document.querySelectorAll("[data-reveal]");

  if (elements.length === 0) {
    return;
  }

  document.documentElement.classList.add("reveal-ready");

  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) {
          return;
        }

        entry.target.classList.add("is-visible");
        observer.unobserve(entry.target);
      });
    },
    { rootMargin: "0px 0px -12% 0px", threshold: 0.1 },
  );

  elements.forEach((element) => observer.observe(element));
};

loadPopoverFallback()
  .catch(() => undefined)
  .finally(() => {
    setupResponsiveMenu();
    closeMenuAfterNavigation();
  });
revealSections();
