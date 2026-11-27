const isPopoverOpen = (element) =>
  element.matches(":is(:popover-open, .\\:popover-open)");

const loadPopoverFallback = async () => {
  if (!("popover" in HTMLElement.prototype)) {
    await import("@oddbird/popover-polyfill");
  }
};

const setupSiteNavigation = () => {
  const header = document.querySelector("[data-site-header]");
  const inner = header?.querySelector(".site-header-inner");
  const menu = document.querySelector("#site-menu");
  const dropdownItem = document.querySelector("[data-nav-dropdown]");
  const dropdownTrigger = dropdownItem?.querySelector(".dropdown-trigger");
  const submenu = dropdownItem?.querySelector("#more-menu");

  if (
    !(header instanceof HTMLElement) ||
    !(inner instanceof HTMLElement) ||
    !(menu instanceof HTMLElement)
  ) {
    return;
  }

  let lastCollapsedWidth = 0;
  let hoverPreviewing = false;

  const positionFallbackSubmenu = () => {
    if (
      !(dropdownTrigger instanceof HTMLButtonElement) ||
      !(submenu instanceof HTMLElement) ||
      !header.classList.contains("is-wide") ||
      CSS.supports("position-area", "block-end")
    ) {
      return;
    }

    const triggerRect = dropdownTrigger.getBoundingClientRect();
    const menuRect = submenu.getBoundingClientRect();
    let top = triggerRect.bottom + 8;
    if (top + menuRect.height > window.innerHeight - 12) {
      top = Math.max(12, triggerRect.top - menuRect.height - 8);
    }
    const left = Math.max(
      12,
      Math.min(
        triggerRect.right - menuRect.width,
        window.innerWidth - menuRect.width - 12,
      ),
    );
    submenu.style.top = `${top}px`;
    submenu.style.left = `${left}px`;
  };

  const syncLayout = (force = false) => {
    const currentlyWide = header.classList.contains("is-wide");
    const headerWidth = header.clientWidth;

    if (!force && !currentlyWide && headerWidth <= lastCollapsedWidth) {
      return;
    }

    const wasMenuOpen = !currentlyWide && isPopoverOpen(menu);
    if (wasMenuOpen) {
      menu.hidePopover();
    }

    header.classList.add("is-measuring");
    header.classList.add("is-wide");
    menu.removeAttribute("popover");

    const brand = inner.querySelector(".brand");
    const availableForMenu =
      brand instanceof HTMLElement
        ? inner.clientWidth - brand.getBoundingClientRect().width - 24
        : inner.clientWidth;
    const fitsInline =
      inner.scrollWidth <= inner.clientWidth &&
      menu.scrollWidth <= availableForMenu;

    if (fitsInline) {
      lastCollapsedWidth = 0;
      if (
        submenu instanceof HTMLElement &&
        dropdownTrigger instanceof HTMLButtonElement
      ) {
        submenu.setAttribute("popover", "auto");
        submenu.hidden = false;
        if (!isPopoverOpen(submenu)) {
          dropdownTrigger.setAttribute("aria-expanded", "false");
        }
      }
    } else {
      header.classList.remove("is-wide");
      lastCollapsedWidth = headerWidth;
      menu.setAttribute("popover", "auto");

      if (
        submenu instanceof HTMLElement &&
        dropdownTrigger instanceof HTMLButtonElement
      ) {
        if (isPopoverOpen(submenu)) {
          submenu.hidePopover();
        }
        submenu.removeAttribute("popover");
        submenu.style.top = "";
        submenu.style.left = "";
        submenu.hidden = true;
        dropdownTrigger.setAttribute("aria-expanded", "false");
      }

      if (wasMenuOpen) {
        menu.showPopover();
      }
    }

    requestAnimationFrame(() => {
      header.classList.remove("is-measuring");
    });
  };

  if (
    dropdownTrigger instanceof HTMLButtonElement &&
    submenu instanceof HTMLElement &&
    dropdownItem instanceof HTMLElement
  ) {
    submenu.addEventListener("toggle", (event) => {
      if (!header.classList.contains("is-wide")) {
        return;
      }
      const open = event.newState === "open";
      dropdownTrigger.setAttribute("aria-expanded", open ? "true" : "false");
      if (open) {
        positionFallbackSubmenu();
      } else {
        hoverPreviewing = false;
      }
    });

    dropdownTrigger.addEventListener("click", (event) => {
      if (header.classList.contains("is-wide")) {
        if (hoverPreviewing && isPopoverOpen(submenu)) {
          hoverPreviewing = false;
          event.preventDefault();
        }
        return;
      }

      const willOpen = submenu.hidden;
      submenu.hidden = !willOpen;
      dropdownTrigger.setAttribute(
        "aria-expanded",
        willOpen ? "true" : "false",
      );
    });

    dropdownItem.addEventListener("pointerenter", (event) => {
      if (
        !header.classList.contains("is-wide") ||
        !window.matchMedia("(hover: hover)").matches ||
        event.pointerType === "touch" ||
        isPopoverOpen(submenu)
      ) {
        return;
      }

      hoverPreviewing = true;
      submenu.showPopover({ source: dropdownTrigger });
    });

    dropdownItem.addEventListener("pointerleave", () => {
      if (hoverPreviewing && isPopoverOpen(submenu)) {
        hoverPreviewing = false;
        submenu.hidePopover();
      }
    });

    dropdownItem.addEventListener("focusout", (event) => {
      if (
        header.classList.contains("is-wide") &&
        event.relatedTarget instanceof Node &&
        !dropdownItem.contains(event.relatedTarget) &&
        isPopoverOpen(submenu)
      ) {
        hoverPreviewing = false;
        submenu.hidePopover();
      }
    });
  }

  menu.addEventListener("click", (event) => {
    const anchor =
      event.target instanceof Element ? event.target.closest("a") : null;
    if (!anchor) {
      return;
    }
    if (!header.classList.contains("is-wide") && isPopoverOpen(menu)) {
      menu.hidePopover();
    }
    if (submenu instanceof HTMLElement && isPopoverOpen(submenu)) {
      submenu.hidePopover();
    }
  });

  if ("ResizeObserver" in window) {
    const observer = new ResizeObserver(() => syncLayout(false));
    observer.observe(header);
    observer.observe(menu);
  } else {
    window.addEventListener("resize", () => syncLayout(true), {
      passive: true,
    });
  }

  syncLayout(true);
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

  const revealInOrAboveViewport = () => {
    const cutoff = window.innerHeight + 80;
    elements.forEach((element) => {
      if (element.classList.contains("is-visible")) {
        return;
      }
      if (element.getBoundingClientRect().top <= cutoff) {
        element.classList.add("is-visible");
      }
    });
  };

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
    { rootMargin: "0px 0px 80px 0px", threshold: 0.01 },
  );

  elements.forEach((element) => observer.observe(element));
  revealInOrAboveViewport();
  window.addEventListener("scroll", revealInOrAboveViewport, { passive: true });
  window.addEventListener("hashchange", () =>
    requestAnimationFrame(revealInOrAboveViewport),
  );
};

const setupConsentAwareAnalytics = () => {
  const bar = document.querySelector("#analytics-consent-bar");
  if (!(bar instanceof HTMLElement)) {
    return;
  }

  const storageKey = "spinellanka_analytics_consent";
  const domain = bar.dataset.analyticsDomain || "";
  const scriptSrc = bar.dataset.analyticsSrc || "";

  if (!domain || !scriptSrc) {
    return;
  }

  const loadPlausibleScript = () => {
    if (
      document.querySelector('script[data-spinellanka-analytics="plausible"]')
    ) {
      return;
    }

    const script = document.createElement("script");
    script.async = true;
    script.defer = true;
    script.setAttribute("fetchpriority", "low");
    script.setAttribute("data-domain", domain);
    script.setAttribute("data-spinellanka-analytics", "plausible");
    script.src = scriptSrc;
    document.head.appendChild(script);
  };

  let choice = null;
  try {
    choice = window.localStorage.getItem(storageKey);
  } catch {
    choice = null;
  }

  if (choice === "accepted") {
    loadPlausibleScript();
  } else if (choice !== "declined") {
    bar.hidden = false;
  }

  const manageButtons = document.querySelectorAll("[data-analytics-manage]");
  manageButtons.forEach((btn) => {
    if (btn instanceof HTMLButtonElement) {
      btn.hidden = false;
      btn.addEventListener("click", () => {
        bar.hidden = false;
        const acceptBtn = bar.querySelector("[data-analytics-accept]");
        if (acceptBtn instanceof HTMLButtonElement) {
          acceptBtn.focus();
        }
      });
    }
  });

  const acceptBtn = bar.querySelector("[data-analytics-accept]");
  const declineBtn = bar.querySelector("[data-analytics-decline]");

  if (acceptBtn instanceof HTMLButtonElement) {
    acceptBtn.addEventListener("click", () => {
      try {
        window.localStorage.setItem(storageKey, "accepted");
      } catch {
        // Ignore storage restrictions
      }
      bar.hidden = true;
      loadPlausibleScript();
    });
  }

  if (declineBtn instanceof HTMLButtonElement) {
    declineBtn.addEventListener("click", () => {
      try {
        window.localStorage.setItem(storageKey, "declined");
      } catch {
        // Ignore storage restrictions
      }
      bar.hidden = true;
    });
  }
};

loadPopoverFallback()
  .catch(() => undefined)
  .finally(() => {
    setupSiteNavigation();
  });
revealSections();
setupConsentAwareAnalytics();
