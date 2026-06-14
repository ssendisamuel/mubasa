(function () {
  const ASSISTANT_SOURCE = "AI Model Developed and Trained by Ssendi";

  const WIDGET_INTRO =
    "Welcome — I'm here to help MUBASA members get straight answers.\n\n" +
    "Ask me about the June 2026 executive elections, nominated candidates, manifesto commitments, " +
    "or how MUBS policies on promotions, leave, science pay, and welfare apply to you.\n\n" +
    "What would you like to know?";

  const defaultSuggestions = [
    "When are the MUBASA elections in June 2026?",
    "Who is running for Deputy Chairperson?",
    "What are the four manifesto pillars?",
    "What does the HR Manual say about promotions?",
  ];

  function initPolicyChat() {
    const pageChat = document.getElementById("ai-assistant-chat");
    const widget = document.getElementById("policy-chat-widget");
    const isPageMode = Boolean(pageChat);

    const toggle = document.getElementById("policy-chat-toggle");
    const panel = document.getElementById("policy-chat-panel");
    const closeBtn = document.getElementById("policy-chat-close");
    const form = document.getElementById("policy-chat-form");
    const input = document.getElementById("policy-chat-input");
    const messages = document.getElementById("policy-chat-messages");
    const suggestionsEl = document.getElementById("policy-chat-suggestions");
    const emptyState = document.getElementById("ai-chat-empty");
    const capabilityGrid = document.getElementById("ai-capability-grid");

    if (!form || !input || !messages || (!isPageMode && (!widget || !toggle || !panel))) {
      return;
    }

    let policiesCache = null;
    let knowledgeCache = null;
    let chatStarted = false;

    function escapeHtml(str) {
      return str
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;");
    }

    function resizeInput() {
      if (input.tagName !== "TEXTAREA") return;
      input.style.height = "auto";
      input.style.height = `${Math.min(input.scrollHeight, 160)}px`;
    }

    function showChatThread() {
      if (chatStarted) return;
      chatStarted = true;
      if (emptyState) emptyState.hidden = true;
      messages.hidden = false;
      if (suggestionsEl) suggestionsEl.hidden = false;
    }

    function addMessage(text, role, source) {
      showChatThread();
      const item = document.createElement("div");
      item.className = `chat-msg chat-msg-${role}${isPageMode ? " chat-msg-page" : ""}`;
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
        btn.className = isPageMode ? "ai-chat-chip" : "chat-suggestion";
        btn.textContent = text;
        btn.addEventListener("click", () => {
          input.value = text;
          form.requestSubmit();
        });
        suggestionsEl.appendChild(btn);
      });
      if (isPageMode && chatStarted) {
        suggestionsEl.hidden = false;
      }
    }

    function isOpen() {
      return panel?.classList.contains("is-open");
    }

    function openPanel() {
      if (isPageMode) return;
      panel.classList.add("is-open");
      panel.removeAttribute("hidden");
      widget.classList.add("is-open");
      toggle.setAttribute("aria-expanded", "true");
      document.body.classList.add("policy-chat-open");
      input.focus({ preventScroll: true });
      bootWidgetChat();
    }

    function closePanel() {
      if (isPageMode) return;
      panel.classList.remove("is-open");
      panel.setAttribute("hidden", "");
      widget.classList.remove("is-open");
      toggle.setAttribute("aria-expanded", "false");
      document.body.classList.remove("policy-chat-open");
    }

    function bootWidgetChat() {
      if (messages.childElementCount === 0) {
        addMessage(WIDGET_INTRO, "bot", ASSISTANT_SOURCE);
        renderSuggestions(defaultSuggestions);
      }
    }

    function submitPrompt(text) {
      input.value = text;
      form.requestSubmit();
    }

    capabilityGrid?.querySelectorAll("[data-prompt]").forEach((btn) => {
      btn.addEventListener("click", () => submitPrompt(btn.getAttribute("data-prompt") || ""));
    });

    if (input.tagName === "TEXTAREA") {
      input.addEventListener("input", resizeInput);
      input.addEventListener("keydown", (event) => {
        if (event.key === "Enter" && !event.shiftKey) {
          event.preventDefault();
          form.requestSubmit();
        }
      });
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

    async function loadKnowledge() {
      if (knowledgeCache) return knowledgeCache;
      try {
        const response = await fetch("data/mubasa-assistant-knowledge.json");
        knowledgeCache = await response.json();
      } catch {
        knowledgeCache = {};
      }
      return knowledgeCache;
    }

    async function answerLocally(query) {
      if (isGreeting(query)) {
        return {
          answer: WIDGET_INTRO,
          source: ASSISTANT_SOURCE,
        };
      }

      const knowledge = await loadKnowledge();
      const tokens = tokenize(query);
      const knowledgeBlob = JSON.stringify(knowledge).toLowerCase();
      const electionTerms = [
        "election", "vote", "voting", "campaign", "debate", "nomination",
        "handover", "roadmap", "candidate", "manifesto", "mubasa", "ssendi", "deputy",
      ];
      const knowledgeScore = tokens.reduce((score, token) => {
        let next = wordMatch(knowledgeBlob, token) ? score + 2 : score;
        if (electionTerms.includes(token)) next += 3;
        return next;
      }, 0);

      if (knowledgeScore >= 3) {
        const candidate = knowledge.campaignCandidate || {};
        const roadmap = (knowledge.electionRoadmap || [])
          .map((step) => `${step.activity} (${step.dates})`)
          .join("\n");
        const deputy = (knowledge.contestedCandidates || {})["Deputy Chairperson"] || [];

        return {
          answer:
            `Here is a quick overview for members.\n\n` +
            `2026 election roadmap:\n${roadmap}\n\n` +
            `Deputy Chairperson: ${deputy.join(" and ")}.\n\n` +
            `${candidate.name || "Ssendi Samuel"}'s manifesto rests on Unity, Welfare, Growth, and Sustainability — ` +
            `with practical commitments on promotions, science pay, healthcare, and a stronger voice for every campus.\n\n` +
            "Ask about any specific pillar, position, or policy for more detail.",
          source: ASSISTANT_SOURCE,
          suggestions: knowledge.suggestedQuestions || defaultSuggestions,
        };
      }

      const policies = await loadPolicies();
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
            "I could not find a precise match in what I have loaded. Try asking about the June 2026 election dates, nominated candidates, manifesto pillars, promotions, leave, science pay, or FASPU.",
          source: ASSISTANT_SOURCE,
          suggestions: defaultSuggestions,
        };
      }

      return {
        answer: `${best.summary}\n\nManifesto alignment: ${best.manifestoAlignment}`,
        source: `${best.title} · ${ASSISTANT_SOURCE}`,
        suggestions: defaultSuggestions,
      };
    }

    if (!isPageMode) {
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
    }

    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      const text = input.value.trim();
      if (!text) return;

      addMessage(text, "user");
      input.value = "";
      resizeInput();

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
            addMessage(result.answer, "bot", result.source || ASSISTANT_SOURCE);
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
        renderSuggestions(local.suggestions || defaultSuggestions);
      } catch {
        addMessage(
          "Sorry — I could not reach the server right now. You can still browse the manifesto and Policy Hub on the main site.",
          "bot",
          ASSISTANT_SOURCE
        );
      } finally {
        submitBtn.disabled = false;
        input.focus();
      }
    });
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initPolicyChat);
  } else {
    initPolicyChat();
  }
})();
