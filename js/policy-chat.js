(function () {
  function initPolicyChat() {
    const widget = document.getElementById("policy-chat-widget");
    const toggle = document.getElementById("policy-chat-toggle");
    const panel = document.getElementById("policy-chat-panel");
    const closeBtn = document.getElementById("policy-chat-close");
    const form = document.getElementById("policy-chat-form");
    const input = document.getElementById("policy-chat-input");
    const messages = document.getElementById("policy-chat-messages");
    const suggestionsEl = document.getElementById("policy-chat-suggestions");

    if (!widget || !toggle || !panel || !form || !input || !messages) {
      return;
    }

    let policiesCache = null;

    const intro =
      "Hello! I am the MUBASA Policy Assistant. Ask me about the MUBS HR Manual, Strategic Plan, Universities Act, Public Service Standing Orders, FASPU agreements, and how they align with Ssendi Samuel's manifesto.";

    const defaultSuggestions = [
      "What does the HR Manual say about promotions?",
      "How does the Strategic Plan support staff growth?",
      "What is FASPU's role in salary harmonisation?",
      "What are my grievance rights at MUBS?",
    ];

    function escapeHtml(str) {
      return str
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;");
    }

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

    function isOpen() {
      return panel.classList.contains("is-open");
    }

    function openPanel() {
      panel.classList.add("is-open");
      panel.removeAttribute("hidden");
      widget.classList.add("is-open");
      toggle.setAttribute("aria-expanded", "true");
      document.body.classList.add("policy-chat-open");
      input.focus({ preventScroll: true });

      if (messages.childElementCount === 0) {
        addMessage(intro, "bot", "Policy Assistant");
        renderSuggestions(defaultSuggestions);
      }
    }

    function closePanel() {
      panel.classList.remove("is-open");
      panel.setAttribute("hidden", "");
      widget.classList.remove("is-open");
      toggle.setAttribute("aria-expanded", "false");
      document.body.classList.remove("policy-chat-open");
    }

    function tokenize(text) {
      const stop = new Set([
        "the", "and", "for", "what", "how", "does", "about", "with", "from",
        "that", "this", "are", "can", "you", "mubs", "tell", "explain", "our",
      ]);
      return String(text)
        .toLowerCase()
        .replace(/[^a-z0-9\s]/g, " ")
        .split(/\s+/)
        .filter((word) => word.length > 2 && !stop.has(word));
    }

    function wordMatch(haystack, term) {
      const escaped = term.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
      return new RegExp(`\\b${escaped}\\b`, "i").test(haystack);
    }

    function scoreText(text, keywords, tokens) {
      const haystack = String(text).toLowerCase();
      let score = 0;
      tokens.forEach((token) => {
        if (wordMatch(haystack, token)) score += 2;
      });
      (keywords || []).forEach((keyword) => {
        if (wordMatch(haystack, String(keyword).toLowerCase())) score += 4;
      });
      return score;
    }

    function isGreeting(text) {
      const q = String(text).trim().toLowerCase();
      if (/^(hi|hello|hey|good\s+(morning|afternoon|evening)|greetings|howdy|thanks|thank\s+you|ok|okay)[!.?\s]*$/i.test(q)) {
        return true;
      }
      return /^how\s+are\s+you[!.?\s]*$/i.test(q);
    }

    async function loadPolicies() {
      if (policiesCache) return policiesCache;
      const response = await fetch("data/policies-index.json");
      policiesCache = await response.json();
      return policiesCache;
    }

    async function answerLocally(query) {
      if (isGreeting(query)) {
        return {
          answer:
            "Hello! I am the MUBASA Policy Assistant. Ask me about promotions, leave, grievances, science pay, the Strategic Plan, or FASPU agreements.",
          source: "Policy Assistant · offline mode",
        };
      }

      const policies = await loadPolicies();
      const tokens = tokenize(query);
      let best = null;
      let bestScore = 0;

      policies.forEach((policy) => {
        const blob = [
          policy.title,
          policy.summary,
          policy.manifestoAlignment,
          ...(policy.keyTopics || []),
          ...(policy.keywords || []),
        ].join(" ");
        const score = scoreText(blob, policy.keywords, tokens);
        if (score > bestScore) {
          bestScore = score;
          best = policy;
        }
      });

      if (!best || bestScore < 2) {
        return {
          answer:
            "I could not find a precise match. Try asking about promotions, leave, grievances, science pay, FASPU, or the Strategic Plan Human Capital pillar.",
          source: "Policy Assistant",
          suggestions: defaultSuggestions,
        };
      }

      return {
        answer: `${best.summary}\n\nManifesto alignment: ${best.manifestoAlignment}`,
        source: best.title,
        suggestions: defaultSuggestions,
      };
    }

    toggle.addEventListener("click", (event) => {
      event.preventDefault();
      event.stopPropagation();
      if (isOpen()) closePanel();
      else openPanel();
    });

    closeBtn?.addEventListener("click", closePanel);

    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape" && isOpen()) closePanel();
    });

    window.openPolicyChat = openPanel;

    form.addEventListener("submit", async (event) => {
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

        if (response.ok) {
          const result = await response.json();
          if (result.ok) {
            const source = result.ai
              ? result.source || "Policy Assistant · Claude AI"
              : result.source || "Policy Assistant · document search";
            addMessage(result.answer, "bot", source);
            renderSuggestions(result.suggestions || defaultSuggestions);
            submitBtn.disabled = false;
            return;
          }
        }
      } catch {
        /* fall through to local search */
      }

      try {
        const local = await answerLocally(text);
        addMessage(local.answer, "bot", local.source);
        renderSuggestions(local.suggestions);
      } catch {
        addMessage(
          "Sorry, I could not load policy data right now. Please browse the Policy Hub section on this page or download the HR Manual.",
          "bot",
          "Policy Assistant"
        );
      } finally {
        submitBtn.disabled = false;
      }
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initPolicyChat);
  } else {
    initPolicyChat();
  }
})();
