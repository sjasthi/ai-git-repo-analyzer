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
        width: min(560px, calc(100vw - 24px));
        height: 680px;
        max-height: calc(100vh - 72px);
        z-index: 1200;
        border: 1px solid #d1d5db;
        border-radius: 14px;
        background: #ffffff;
        box-shadow: 0 22px 42px rgba(0, 0, 0, 0.24);
        display: none;
        flex-direction: column;
        overflow: hidden;
    }

    @media (max-width: 768px) {
        .site-chat-panel {
            right: 10px;
            bottom: 66px;
            width: calc(100vw - 20px);
            height: min(78vh, 720px);
            max-height: calc(100vh - 80px);
        }
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

    .site-chat-prompts {
        display: flex;
        flex-wrap: wrap;
        gap: 0.45rem;
        padding: 0.7rem 0.7rem 0;
        background: #ffffff;
    }

    .site-chat-prompt {
        border: 1px solid #bfdbfe;
        border-radius: 999px;
        background: #eff6ff;
        color: #1d4ed8;
        padding: 0.38rem 0.6rem;
        font-size: 0.76rem;
        font-weight: 600;
        cursor: pointer;
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
    <i class="fas fa-circle-question"></i> Help Center
</button>

<div id="site-chat-panel" class="site-chat-panel" aria-hidden="true">
    <div class="site-chat-header">
        <h3 class="site-chat-title">Help Center</h3>
        <button type="button" id="site-chat-close" class="site-chat-close" aria-label="Close chat">&times;</button>
    </div>
    <div id="site-chat-messages" class="site-chat-messages" aria-live="polite"></div>
    <div class="site-chat-prompts" id="site-chat-prompts" aria-label="Help Center questions"></div>
    <div class="site-chat-input-wrap">
        <input id="site-chat-input" class="site-chat-input" type="text" placeholder="Search or type a Help Center question" maxlength="1000">
        <button type="button" id="site-chat-send" class="site-chat-send">Ask</button>
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
    const promptsWrap = document.getElementById('site-chat-prompts');
    let helpFaqs = [];

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

    function renderFaqButtons() {
        if (!promptsWrap) {
            return;
        }
        promptsWrap.innerHTML = '';
        helpFaqs.forEach(function (faq) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'site-chat-prompt';
            btn.setAttribute('data-question-id', String(faq.id || ''));
            btn.setAttribute('data-question', String(faq.question || ''));
            btn.textContent = String(faq.question || 'Question');
            btn.addEventListener('click', function () {
                const question = String(faq.question || '').trim();
                if (question === '') {
                    return;
                }
                input.value = question;
                sendMessage(String(faq.id || ''), question);
            });
            promptsWrap.appendChild(btn);
        });
    }

    function loadHelpCenterFaqs() {
        return fetch('api/chat_assistant.php', { method: 'GET' })
            .then(function (res) {
                return res.json().then(function (data) {
                    return { status: res.status, data: data };
                });
            })
            .then(function (result) {
                if (result.status >= 400 || !result.data || result.data.ok !== true || !Array.isArray(result.data.faqs)) {
                    return;
                }
                helpFaqs = result.data.faqs.slice(0, 20);
                renderFaqButtons();
            })
            .catch(function () {
                // Keep static fallback behavior if loading FAQ list fails.
            });
    }

    function sendMessage(questionId, forcedQuestion) {
        const question = String(forcedQuestion || input.value || '').trim();
        if (question === '') {
            return;
        }

        appendMessage('user', question);
        input.value = '';
        sendBtn.disabled = true;
        sendBtn.textContent = '...';

        const payload = questionId
            ? { question_id: questionId, question: question }
            : { question: question };

        fetch('api/chat_assistant.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(function (res) {
            return res.json().then(function (data) {
                return { status: res.status, data: data };
            });
        })
        .then(function (result) {
            if (result.status >= 400 || !result.data || result.data.ok !== true) {
                appendMessage('assistant', 'Error: Unable to load Help Center answer right now.');
                return;
            }
            appendMessage('assistant', String(result.data.answer || 'No response generated.'));
        })
        .catch(function () {
            appendMessage('assistant', 'Error: Failed to reach Help Center endpoint.');
        })
        .finally(function () {
            sendBtn.disabled = false;
            sendBtn.textContent = 'Ask';
        });
    }

    launcher.addEventListener('click', function () {
        panel.classList.toggle('open');
        panel.setAttribute('aria-hidden', panel.classList.contains('open') ? 'false' : 'true');
        if (panel.classList.contains('open') && messages.childElementCount === 0) {
            appendMessage('assistant', 'Help Center is ready. Choose a question below or type one to search.');
            loadHelpCenterFaqs();
        }
    });

    if (closeBtn) {
        closeBtn.addEventListener('click', function () {
            panel.classList.remove('open');
            panel.setAttribute('aria-hidden', 'true');
        });
    }

    sendBtn.addEventListener('click', function () {
        sendMessage('', '');
    });
    input.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            sendMessage('', '');
        }
    });
})();
</script>
