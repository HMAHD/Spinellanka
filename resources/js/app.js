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
    setupResponsiveMenu();
    closeMenuAfterNavigation();
  });
revealSections();
setupConsentAwareAnalytics();
