(function () {
  const toggle = document.querySelector(".nav-toggle");
  const nav = document.querySelector(".site-nav");
  const links = document.querySelectorAll(".site-nav a");

  if (!toggle || !nav) return;

  toggle.addEventListener("click", () => {
    const open = nav.classList.toggle("open");
    toggle.setAttribute("aria-expanded", String(open));
    toggle.setAttribute("aria-label", open ? "Close menu" : "Open menu");
  });

  links.forEach((link) => {
    link.addEventListener("click", () => {
      nav.classList.remove("open");
      toggle.setAttribute("aria-expanded", "false");
      toggle.setAttribute("aria-label", "Open menu");
    });
  });

  const header = document.querySelector(".site-header");
  let lastScroll = 0;

  window.addEventListener(
    "scroll",
    () => {
      const current = window.scrollY;
      if (!header) return;

      header.style.boxShadow =
        current > 12 ? "0 10px 30px rgba(0, 0, 0, 0.25)" : "none";
      lastScroll = current;
    },
    { passive: true }
  );
})();
