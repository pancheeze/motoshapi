(function () {
  'use strict';

  const STORAGE_KEY = 'ms_chatbot_history_v1';
  const STORAGE_MAX_MESSAGES = 50;

  function $(id) {
    return document.getElementById(id);
  }

  function setHidden(el, hidden) {
    if (!el) return;
    if (hidden) {
      el.setAttribute('hidden', '');
    } else {
      el.removeAttribute('hidden');
    }
  }

  function appendBubble(messagesEl, role, text) {
    const bubble = document.createElement('div');
    bubble.className = 'ms-chatbot-bubble ' + role;
    bubble.textContent = text;
    messagesEl.appendChild(bubble);
    messagesEl.scrollTop = messagesEl.scrollHeight;
    return bubble;
  }

  function loadHistory() {
    try {
      const raw = sessionStorage.getItem(STORAGE_KEY);
      if (!raw) return [];
      const parsed = JSON.parse(raw);
      if (!Array.isArray(parsed)) return [];
      return parsed
        .filter((m) => m && (m.role === 'user' || m.role === 'assistant') && typeof m.text === 'string')
        .slice(-STORAGE_MAX_MESSAGES);
    } catch {
      return [];
    }
  }

  function saveHistory(history) {
    try {
      sessionStorage.setItem(STORAGE_KEY, JSON.stringify(history.slice(-STORAGE_MAX_MESSAGES)));
    } catch {
      // Ignore storage errors (private mode, full quota, etc.)
    }
  }

  function pushHistory(history, role, text) {
    history.push({ role, text: String(text ?? '') });
    if (history.length > STORAGE_MAX_MESSAGES) {
      history.splice(0, history.length - STORAGE_MAX_MESSAGES);
    }
    saveHistory(history);
  }

  async function sendToBackend(message) {
    const res = await fetch('chatbot.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ message }),
    });

    let data;
    try {
      data = await res.json();
    } catch {
      data = null;
    }

    if (!res.ok) {
      const err = (data && (data.error || data.details)) ? (data.error || data.details) : 'Request failed.';
      throw new Error(err);
    }

    if (!data || typeof data.reply !== 'string') {
      throw new Error('Unexpected server response.');
    }

    return data.reply;
  }

  function init() {
    const root = $('ms-chatbot');
    const toggle = $('ms-chatbot-toggle');
    const panel = $('ms-chatbot-panel');
    const closeBtn = $('ms-chatbot-close');
    const form = $('ms-chatbot-form');
    const input = $('ms-chatbot-input');
    const sendBtn = $('ms-chatbot-send');
    const messages = $('ms-chatbot-messages');

    if (!root || !toggle || !panel || !form || !input || !messages) return;

    let open = false;
    const history = loadHistory();

    function setOpen(next) {
      open = next;
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      setHidden(panel, !open);
      if (open) {
        input.focus();

        if (messages.childElementCount === 0) {
          if (history.length > 0) {
            for (const m of history) {
              appendBubble(messages, m.role, m.text);
            }
          } else {
            const greet = 'Hi! How can I help you today? You can ask about products, prices, payment methods, or your cart.';
            appendBubble(messages, 'assistant', greet);
            pushHistory(history, 'assistant', greet);
          }
        }
      }
    }

    toggle.addEventListener('click', function () {
      setOpen(!open);
    });

    if (closeBtn) {
      closeBtn.addEventListener('click', function () {
        setOpen(false);
      });
    }

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && open) {
        setOpen(false);
      }
    });

    form.addEventListener('submit', async function (e) {
      e.preventDefault();
      const text = input.value.trim();
      if (!text) return;

      appendBubble(messages, 'user', text);
      pushHistory(history, 'user', text);
      input.value = '';
      input.focus();

      const typing = appendBubble(messages, 'assistant', 'Typing…');

      try {
        if (sendBtn) sendBtn.disabled = true;
        const reply = await sendToBackend(text);
        typing.textContent = reply;
        pushHistory(history, 'assistant', reply);
      } catch (err) {
        const msg = 'Sorry — I could not reach the chatbot service. ' + (err && err.message ? err.message : '');
        typing.textContent = msg;
        pushHistory(history, 'assistant', msg);
      } finally {
        if (sendBtn) sendBtn.disabled = false;
      }
    });

    // start closed
    setOpen(false);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
