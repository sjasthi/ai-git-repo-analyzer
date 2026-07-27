<style>
    .site-chat-launcher {
        position: fixed;
        right: 16px;
        bottom: 16px;
        z-index: 1200;
        border: 0;
        border-radius: 999px;
        background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
        color: #fff;
        padding: 0.7rem 0.95rem;
        box-shadow: 0 10px 24px rgba(37, 99, 235, 0.35);
        font-size: 0.9rem;
        font-weight: 600;
        cursor: pointer;
    }

    .site-chat-panel {
        position: fixed;
        right: 16px;
        bottom: 72px;
        width: min(420px, calc(100vw - 32px));
        height: 520px;
        max-height: calc(100vh - 96px);
        z-index: 1200;
        border: 1px solid #d1d5db;
        border-radius: 14px;
        background: #ffffff;
        box-shadow: 0 22px 42px rgba(0, 0, 0, 0.24);
        display: none;
        flex-direction: column;
        overflow: hidden;
    }

    .site-chat-panel.open {
        display: flex;
    }

    .site-chat-header {
        padding: 0.7rem 0.85rem;
        background: #eff6ff;
        border-bottom: 1px solid #dbeafe;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 0.6rem;
    }

    .site-chat-title {
        margin: 0;
        font-size: 0.95rem;
        font-weight: 700;
        color: #1e3a8a;
    }

    .site-chat-close {
        border: 0;
        background: transparent;
        color: #1e3a8a;
        cursor: pointer;
        font-size: 1.05rem;
        line-height: 1;
    }

    .site-chat-messages {
        flex: 1;
        overflow-y: auto;
        padding: 0.8rem;
        background: #f8fafc;
    }

    .site-chat-msg {
        margin-bottom: 0.6rem;
        display: flex;
    }

    .site-chat-msg.user {
        justify-content: flex-end;
    }

    .site-chat-bubble {
        max-width: 88%;
        border: 1px solid #d1d5db;
        border-radius: 10px;
        padding: 0.55rem 0.65rem;
        font-size: 0.85rem;
        line-height: 1.38;
        white-space: pre-wrap;
        word-break: break-word;
        background: #fff;
        color: #111827;
    }

    .site-chat-msg.user .site-chat-bubble {
        background: #dbeafe;
        color: #1e3a8a;
        border-color: #93c5fd;
    }

    .site-chat-input-wrap {
        border-top: 1px solid #e5e7eb;
        padding: 0.7rem;
        display: flex;
        gap: 0.45rem;
        background: #ffffff;
    }

    .site-chat-input {
        flex: 1;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        padding: 0.5rem 0.6rem;
        font-size: 0.9rem;
    }

    .site-chat-send {
        border: 0;
        border-radius: 8px;
        background: #2563eb;
        color: #fff;
        padding: 0.5rem 0.8rem;
        font-size: 0.86rem;
        font-weight: 600;
        cursor: pointer;
    }
</style>

<button type="button" id="site-chat-launcher" class="site-chat-launcher">
    <i class="fas fa-robot"></i> Chat Assistant
</button>

<div id="site-chat-panel" class="site-chat-panel" aria-hidden="true">
    <div class="site-chat-header">
        <h3 class="site-chat-title">AI Feature: Chat Assistant</h3>
        <button type="button" id="site-chat-close" class="site-chat-close" aria-label="Close chat">&times;</button>
    </div>
    <div id="site-chat-messages" class="site-chat-messages" aria-live="polite"></div>
    <div class="site-chat-input-wrap">
        <input id="site-chat-input" class="site-chat-input" type="text" placeholder="Ask: How do I use this website?" maxlength="1000">
        <button type="button" id="site-chat-send" class="site-chat-send">Send</button>
    </div>
</div>

<script>
(function () {
    const launcher = document.getElementById('site-chat-launcher');
    const panel = document.getElementById('site-chat-panel');
    const closeBtn = document.getElementById('site-chat-close');
    const messages = document.getElementById('site-chat-messages');
    const input = document.getElementById('site-chat-input');
    const sendBtn = document.getElementById('site-chat-send');

    if (!launcher || !panel || !messages || !input || !sendBtn) {
        return;
    }

    function esc(text) {
        const span = document.createElement('span');
        span.textContent = String(text || '');
        return span.innerHTML;
    }

    function appendMessage(role, text) {
        const row = document.createElement('div');
        row.className = 'site-chat-msg ' + (role === 'user' ? 'user' : 'assistant');
        row.innerHTML = '<div class="site-chat-bubble">' + esc(text) + '</div>';
        messages.appendChild(row);
        messages.scrollTop = messages.scrollHeight;
    }

    function getContext() {
        const root = window.latestScanData || {};
        return {
            score: root?.scan?.summary_score ?? null,
            findings: Array.isArray(root?.findings) ? root.findings.slice(0, 80) : [],
            recommendations: Array.isArray(root?.recommendations) ? root.recommendations.slice(0, 80) : [],
            checks: Array.isArray(root?.checks) ? root.checks.slice(0, 120) : []
        };
    }

    function sendMessage() {
        const question = String(input.value || '').trim();
        if (question === '') {
            return;
        }

        appendMessage('user', question);
        input.value = '';
        sendBtn.disabled = true;
        sendBtn.textContent = '...';

        fetch('api/chat_assistant.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ question: question, context: getContext() })
        })
        .then(function (res) {
            return res.json().then(function (data) {
                return { status: res.status, data: data };
            });
        })
        .then(function (result) {
            if (result.status >= 400 || !result.data || result.data.ok !== true) {
                appendMessage('assistant', 'Error: Unable to generate a response right now.');
                return;
            }
            appendMessage('assistant', String(result.data.answer || 'No response generated.'));
        })
        .catch(function () {
            appendMessage('assistant', 'Error: Failed to reach chat assistant endpoint.');
        })
        .finally(function () {
            sendBtn.disabled = false;
            sendBtn.textContent = 'Send';
        });
    }

    launcher.addEventListener('click', function () {
        panel.classList.toggle('open');
        panel.setAttribute('aria-hidden', panel.classList.contains('open') ? 'false' : 'true');
        if (panel.classList.contains('open') && messages.childElementCount === 0) {
            appendMessage('assistant', 'Chat Assistant is ready. Ask about score, findings, priorities, or how to use this website.');
        }
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', function () {
            panel.classList.remove('open');
            panel.setAttribute('aria-hidden', 'true');
        });
    }

    sendBtn.addEventListener('click', sendMessage);
    input.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            sendMessage();
        }
    });
})();
</script>
