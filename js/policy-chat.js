(function () {
  const widget = document.getElementById("policy-chat-widget");
  if (!widget) return;

  const toggle = document.getElementById("policy-chat-toggle");
  const panel = document.getElementById("policy-chat-panel");
  const closeBtn = document.getElementById("policy-chat-close");
  const form = document.getElementById("policy-chat-form");
  const input = document.getElementById("policy-chat-input");
  const messages = document.getElementById("policy-chat-messages");
  const suggestionsEl = document.getElementById("policy-chat-suggestions");

  const intro =
    "Hello! I am the MUBASA Policy Assistant. Ask me about the MUBS HR Manual, Strategic Plan, Universities Act, Public Service Standing Orders, FASPU agreements, and how they align with Ssendi Samuel's manifesto.";

  function addMessage(text, role, source) {
    const item = document.createElement("div");
    item.className = `chat-msg chat-msg-${role}`;
    item.innerHTML = `<p>${escapeHtml(text).replace(/\n/g, "<br>")}</p>`;
    if (source && role === "bot") {
      const meta = document.createElement("span");
      meta.className = "chat-msg-source";
      meta.textContent = source;
      item.appendChild(meta);
    }
    messages.appendChild(item);
    messages.scrollTop = messages.scrollHeight;
  }

  function escapeHtml(str) {
    return str
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function renderSuggestions(list) {
    if (!suggestionsEl || !list?.length) return;
    suggestionsEl.innerHTML = "";
    list.slice(0, 4).forEach((text) => {
      const btn = document.createElement("button");
      btn.type = "button";
      btn.className = "chat-suggestion";
      btn.textContent = text;
      btn.addEventListener("click", () => {
        input.value = text;
        form.requestSubmit();
      });
      suggestionsEl.appendChild(btn);
    });
  }

  function openPanel() {
    panel.hidden = false;
    toggle.setAttribute("aria-expanded", "true");
    input.focus();
    if (messages.childElementCount === 0) {
      addMessage(intro, "bot", "Policy Assistant");
      renderSuggestions([
        "What does the HR Manual say about promotions?",
        "How does the Strategic Plan support staff growth?",
        "What is FASPU's role in salary harmonisation?",
        "What are my grievance rights at MUBS?",
      ]);
    }
  }

  function closePanel() {
    panel.hidden = true;
    toggle.setAttribute("aria-expanded", "false");
  }

  toggle?.addEventListener("click", () => {
    if (panel.hidden) openPanel();
    else closePanel();
  });
  closeBtn?.addEventListener("click", closePanel);

  form?.addEventListener("submit", async (event) => {
    event.preventDefault();
    const text = input.value.trim();
    if (!text) return;

    addMessage(text, "user");
    input.value = "";
    const submitBtn = form.querySelector('button[type="submit"]');
    submitBtn.disabled = true;

    try {
      const response = await fetch("api/policy-chat.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ message: text }),
      });
      const result = await response.json();
      if (result.ok) {
        addMessage(result.answer, "bot", result.source);
        renderSuggestions(result.suggestions);
      } else {
        addMessage(result.error || "Sorry, I could not answer that right now.", "bot", "Policy Assistant");
      }
    } catch {
      addMessage(
        "The policy assistant needs the live server to search the full HR Manual. Browse the Policy Hub below, or deploy the site to use chat on mubasa.ssendi.dev.",
        "bot",
        "Policy Assistant"
      );
    } finally {
      submitBtn.disabled = false;
    }
  });
})();
