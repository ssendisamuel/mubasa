(function () {
  const toggle = document.querySelector(".nav-toggle");
  const nav = document.querySelector(".site-nav");
  const links = document.querySelectorAll(".site-nav a");

  if (toggle && nav) {
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
  }

  const header = document.querySelector(".site-header");
  window.addEventListener(
    "scroll",
    () => {
      if (!header) return;
      header.style.boxShadow =
        window.scrollY > 12 ? "0 10px 30px rgba(0, 0, 0, 0.25)" : "none";
    },
    { passive: true }
  );

  const form = document.getElementById("feedback-form");
  const status = document.getElementById("form-status");

  if (form && status) {
    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      status.textContent = "";
      status.className = "form-status";

      const data = Object.fromEntries(new FormData(form).entries());
      const submitBtn = form.querySelector('button[type="submit"]');

      if (!data.pillar || !data.message || !String(data.message).trim()) {
        status.textContent = "Please select a pillar and share your expectations.";
        status.classList.add("form-status-error");
        return;
      }

      submitBtn.disabled = true;
      submitBtn.textContent = "Sending…";

      try {
        const response = await fetch("api/submit-feedback.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(data),
        });

        const result = await response.json();

        if (result.ok) {
          status.textContent = result.message;
          status.classList.add("form-status-success");
          form.reset();
        } else {
          status.textContent = result.error || "Something went wrong. Please try again.";
          status.classList.add("form-status-error");
        }
      } catch {
        status.textContent =
          "Could not reach the server. On local preview, the form works after deploy. Email sssendi@mubs.ac.ug instead.";
        status.classList.add("form-status-error");
      } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = "Submit feedback";
      }
    });
  }
})();
