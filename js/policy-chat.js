(function () {
  const ASSISTANT_SOURCE = "AI Model Developed and Trained by Ssendi";

  function greetingReply(text) {
    const q = String(text).trim().toLowerCase();
    if (/^how\s+are\s+(you|u)(?:\s+doing)?[!.?\s]*$/i.test(q)) {
      return (
        "I'm doing well, thank you for asking — ready to help whenever you need me.\n\n" +
        "What's on your mind? Elections, manifesto, candidates, or something at MUBS?"
      );
    }
    if (/^(thanks|thank\s+you|thx)[!.?\s]*$/i.test(q)) {
      return "You're welcome. Feel free to ask anything else.";
    }
    if (/^good\s+(morning|afternoon|evening)[!.?\s]*$/i.test(q)) {
      return (
        "Good to hear from you. I'm the MUBASA AI Assistant — happy to help with the June elections, " +
        "manifesto, or staff matters at MUBS."
      );
    }
    return (
      "Hello — good to meet you.\n\n" +
      "I'm the MUBASA AI Assistant. Ask me about the **June 2026 elections**, **candidates**, " +
      "**manifesto**, or **your rights at MUBS** — whatever you need."
    );
  }

  const defaultSuggestions = [
    "When are the MUBASA elections in June 2026?",
    "Who is running for Deputy Chairperson?",
    "Why vote Arinda and Ssendi as a team?",
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
    const scrollContainer = isPageMode
      ? document.querySelector(".ai-chat-messages-wrap")
      : messages;

    if (!form || !input || !messages || (!isPageMode && (!widget || !toggle || !panel))) {
      return;
    }

    let policiesCache = null;
    let knowledgeCache = null;
    let chatStarted = false;
    let typingNode = null;

    function escapeHtml(str) {
      return str
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;");
    }

    function formatInline(text) {
      return text
        .replace(/\*\*(.+?)\*\*/g, "<strong>$1</strong>")
        .replace(/(^|[^*])\*(?!\s)(.+?)(?<!\s)\*(?!\*)/g, "$1<em>$2</em>");
    }

    function formatMessage(text) {
      const escaped = escapeHtml(String(text)).replace(/\r\n/g, "\n");
      const blocks = escaped.split(/\n\n+/);

      return blocks
        .map((block) => {
          const lines = block.split("\n").filter((line) => line.trim() !== "");
          if (lines.length === 0) return "";

          const isBulletList = lines.every((line) => /^\s*-\s+/.test(line));
          if (isBulletList) {
            const items = lines
              .map((line) => line.replace(/^\s*-\s+/, ""))
              .map((line) => `<li>${formatInline(line)}</li>`)
              .join("");
            return `<ul>${items}</ul>`;
          }

          if (lines.length === 1) {
            return `<p>${formatInline(lines[0])}</p>`;
          }

          return `<p>${lines.map((line) => formatInline(line)).join("<br>")}</p>`;
        })
        .filter(Boolean)
        .join("");
    }

    function resizeInput() {
      if (input.tagName !== "TEXTAREA") return;
      input.style.height = "auto";
      input.style.height = `${Math.min(input.scrollHeight, 160)}px`;
    }

    function scrollToLatest(anchor) {
      const scroller = scrollContainer || messages;
      if (!scroller) return;

      requestAnimationFrame(() => {
        requestAnimationFrame(() => {
          if (anchor) {
            anchor.scrollIntoView({ behavior: "smooth", block: "end" });
          }
          scroller.scrollTo({
            top: scroller.scrollHeight,
            behavior: "smooth",
          });
        });
      });
    }

    function showChatThread() {
      if (chatStarted) return;
      chatStarted = true;
      if (emptyState) emptyState.hidden = true;
      messages.hidden = false;
      if (suggestionsEl) suggestionsEl.hidden = false;
      scrollToLatest(messages);
    }

    function showTyping() {
      showChatThread();
      if (typingNode) return typingNode;

      typingNode = document.createElement("div");
      typingNode.className = `chat-msg chat-msg-bot chat-msg-typing${isPageMode ? " chat-msg-page" : ""}`;
      typingNode.innerHTML = '<div class="chat-msg-body"><p class="chat-typing"><span></span><span></span><span></span></p></div>';
      messages.appendChild(typingNode);
      scrollToLatest(typingNode);
      return typingNode;
    }

    function hideTyping() {
      if (typingNode?.parentNode) {
        typingNode.parentNode.removeChild(typingNode);
      }
      typingNode = null;
    }

    function addMessage(text, role, source) {
      hideTyping();
      showChatThread();
      const item = document.createElement("div");
      item.className = `chat-msg chat-msg-${role}${isPageMode ? " chat-msg-page" : ""}`;
      item.innerHTML = `<div class="chat-msg-body">${formatMessage(text)}</div>`;
      if (source && role === "bot") {
        const meta = document.createElement("span");
        meta.className = "chat-msg-source";
        meta.textContent = source;
        item.appendChild(meta);
      }
      messages.appendChild(item);
      scrollToLatest(item);
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
        addMessage(greetingReply("hello"), "bot", ASSISTANT_SOURCE);
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
      if (/\b(today|principal|dean|election|candidate|manifesto|who|what|when|where|why|tell|about|current|news|mubs|mubasa)\b/i.test(q)) {
        return false;
      }
      if (/^(hi|hello|hey|good\s+(morning|afternoon|evening)|greetings|howdy|thanks|thank\s+you|ok|okay)[!.?\s]*$/i.test(q)) {
        return true;
      }
      return /^how\s+are\s+(you|u)(?:\s+doing)?[!.?\s]*$/i.test(q);
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
          answer: greetingReply(query),
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
      showTyping();

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
        hideTyping();
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
