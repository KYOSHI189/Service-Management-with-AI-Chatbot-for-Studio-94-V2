<style>
    /* ===== CHATBOT STYLES ===== */
    .chat-toggle {
        position: fixed;
        bottom: 24px;
        right: 24px;
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: linear-gradient(135deg, #6C3CE1, #9B59B6);
        color: white;
        border: none;
        font-size: 28px;
        cursor: pointer;
        box-shadow: 0 4px 15px rgba(108, 60, 225, 0.4);
        z-index: 9999;
        transition: transform 0.3s ease;
    }

    .chat-toggle:hover {
        transform: scale(1.1);
    }

    .chatbot-widget {
        position: fixed;
        bottom: 100px;
        right: 24px;
        width: 400px;
        max-width: 90vw;
        height: 580px;
        max-height: 80vh;
        background: #1a1a2e;
        border-radius: 16px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
        display: none;
        flex-direction: column;
        z-index: 9998;
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, 0.1);
        transform: translate3d(0, 0, 0);
        touch-action: none;
    }

    .chat-header {
        background: linear-gradient(135deg, #6C3CE1, #9B59B6);
        padding: 14px 18px;
        color: white;
        display: flex;
        justify-content: space-between;
        align-items: center;
        cursor: grab;
        user-select: none;
        flex-shrink: 0;
    }

    .chat-header:active {
        cursor: grabbing;
    }

    .chat-header-actions {
        display: flex;
        gap: 8px;
        align-items: center;
    }

    .chat-header-actions button {
        background: none;
        border: none;
        color: white;
        cursor: pointer;
        font-size: 16px;
        padding: 4px 6px;
        border-radius: 4px;
        transition: background 0.3s ease;
    }

    .chat-header-actions button:hover {
        background: rgba(255, 255, 255, 0.15);
    }

    .chat-messages {
        flex: 1;
        padding: 16px;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 10px;
        background: #16213e;
        min-height: 0;
    }

    .chat-messages::-webkit-scrollbar {
        width: 4px;
    }

    .chat-messages::-webkit-scrollbar-thumb {
        background: #6C3CE1;
        border-radius: 4px;
    }

    .chat-msg {
        max-width: 85%;
        padding: 10px 14px;
        border-radius: 14px;
        font-size: 14px;
        line-height: 1.6;
        word-wrap: break-word;
        animation: fadeIn 0.3s ease;
    }

    .chat-msg.bot {
        background: #2a2a4a;
        color: #e0e0e0;
        align-self: flex-start;
        border-bottom-left-radius: 4px;
    }

    .chat-msg.user {
        background: linear-gradient(135deg, #6C3CE1, #9B59B6);
        color: white;
        align-self: flex-end;
        border-bottom-right-radius: 4px;
    }

    .chat-msg.bot strong {
        color: #B388FF;
    }

    .chat-msg .timestamp {
        font-size: 10px;
        opacity: 0.5;
        margin-top: 4px;
        display: block;
    }

    .booking-confirmation {
        background: rgba(108, 60, 225, 0.2);
        border: 1px solid #6C3CE1;
        border-radius: 8px;
        padding: 10px;
        margin-top: 8px;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(8px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .chat-input-area {
        display: flex;
        padding: 12px 16px;
        gap: 10px;
        background: #1a1a2e;
        border-top: 1px solid rgba(255, 255, 255, 0.08);
        flex-shrink: 0;
    }

    .chat-input-area input {
        flex: 1;
        padding: 10px 14px;
        border-radius: 24px;
        border: 1px solid rgba(255, 255, 255, 0.15);
        background: #2a2a4a;
        color: white;
        font-size: 14px;
        outline: none;
        transition: border-color 0.3s ease;
    }

    .chat-input-area input::placeholder {
        color: #888;
    }

    .chat-input-area input:focus {
        border-color: #6C3CE1;
    }

    .chat-input-area button {
        width: 44px;
        height: 44px;
        border-radius: 50%;
        border: none;
        background: linear-gradient(135deg, #6C3CE1, #9B59B6);
        color: white;
        font-size: 20px;
        cursor: pointer;
        transition: transform 0.2s ease;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .chat-input-area button:hover {
        transform: scale(1.05);
    }

    .typing-indicator {
        display: flex;
        gap: 4px;
        padding: 8px 0;
    }

    .typing-indicator span {
        width: 8px;
        height: 8px;
        background: #6C3CE1;
        border-radius: 50%;
        animation: typingBounce 1.4s infinite;
    }

    .typing-indicator span:nth-child(2) {
        animation-delay: 0.2s;
    }

    .typing-indicator span:nth-child(3) {
        animation-delay: 0.4s;
    }

    @keyframes typingBounce {
        0%, 60%, 100% {
            transform: translateY(0);
            opacity: 0.4;
        }
        30% {
            transform: translateY(-8px);
            opacity: 1;
        }
    }

    .quick-replies {
        padding: 8px 12px;
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
        background: #1a1a2e;
        border-top: 1px solid rgba(255, 255, 255, 0.05);
        flex-shrink: 0;
    }

    .quick-btn {
        background: #2a2a4a;
        border: 1px solid #6C3CE1;
        color: #e0e0e0;
        padding: 5px 14px;
        border-radius: 20px;
        font-size: 12px;
        cursor: pointer;
        transition: all 0.3s ease;
        font-family: inherit;
        white-space: nowrap;
    }

    .quick-btn:hover {
        background: #6C3CE1;
        color: white;
        transform: scale(1.05);
    }

    /* ===== RESPONSIVE ===== */
    @media (max-width: 480px) {
        .chatbot-widget {
            bottom: 80px;
            right: 10px;
            width: calc(100vw - 20px);
            height: 60vh;
            max-height: 70vh;
        }

        .chat-toggle {
            bottom: 16px;
            right: 16px;
            width: 52px;
            height: 52px;
            font-size: 24px;
        }

        .quick-replies {
            gap: 4px;
        }
        .quick-btn {
            font-size: 10px;
            padding: 4px 10px;
        }
    }
</style>

<!-- ===== CHATBOT TOGGLE BUTTON ===== -->
<button class="chat-toggle" id="chat-toggle-btn" onclick="toggleChatbot()">💬</button>

<!-- ===== CHATBOT WIDGET ===== -->
<div id="chatbot-widget" class="chatbot-widget" style="display:none;">
    <div class="chat-header" id="chat-header" style="cursor:grab;">
        <div style="display:flex;align-items:center;gap:8px;">
            <span style="font-size:18px;">🤖</span>
            <div>
                <strong>SnapBot AI</strong>
                <div style="font-size:10px;opacity:0.6;margin-top:1px;">Powered by Gemini</div>
            </div>
        </div>
        <div class="chat-header-actions">
            <button onclick="clearChatHistory()" title="Clear chat history">🗑️</button>
            <button onclick="toggleChatbot()" style="background:none;border:none;color:white;cursor:pointer;font-size:18px;padding:4px;">✕</button>
        </div>
    </div>
    
    <div id="chat-messages" class="chat-messages">
        <!-- Messages will be loaded here -->
    </div>
    
    <div class="quick-replies">
        <button onclick="sendQuickReply('📸 What are your packages?')" class="quick-btn">📸 Packages</button>
        <button onclick="sendQuickReply('📍 Where are you located?')" class="quick-btn">📍 Location</button>
        <button onclick="sendQuickReply('📅 I want to book a session')" class="quick-btn">📅 Book Now</button>
        <button onclick="sendQuickReply('💰 How much is Self-Shoot?')" class="quick-btn">💰 Prices</button>
    </div>
    
    <div class="chat-input-area">
        <input type="text" id="chat-input" placeholder="Type a message..." onkeypress="if(event.key==='Enter') sendChatMsg()"/>
        <button onclick="sendChatMsg()" title="Send message">➤</button>
    </div>
</div>

<script>
// ============================================================
// FORMAT BOT REPLY — Convert \n to <br> at escape HTML
// ============================================================
function escapeAndFormat(text) {
    if (!text) return '';

    // Escape HTML first (para hindi ma-inject ang HTML)
    const div = document.createElement('div');
    div.textContent = text;
    let safe = div.innerHTML;

    // Convert newlines to <br>
    safe = safe.replace(/\n/g, '<br>');

    // Bold the section headers para lumabas nang maayos
    safe = safe.replace(/📌 Booked schedule:/g, '<strong>📌 Booked schedule:</strong>');
    safe = safe.replace(/✅ Available time:/g, '<strong>✅ Available time:</strong>');

    return safe;
}

// ============================================================
// CHAT HISTORY MANAGEMENT (localStorage)
// ============================================================

function loadChatHistory() {
    try {
        const history = localStorage.getItem('snapbot_chat_history');
        if (history) {
            return JSON.parse(history);
        }
    } catch (e) {
        console.error('Error loading history:', e);
    }
    return [];
}

function saveChatHistory(messages) {
    try {
        localStorage.setItem('snapbot_chat_history', JSON.stringify(messages));
    } catch (e) {
        console.error('Error saving history:', e);
    }
}

function clearChatHistory() {
    if (confirm('Clear all chat history?')) {
        localStorage.removeItem('snapbot_chat_history');
        const msgContainer = document.getElementById('chat-messages');
        msgContainer.innerHTML = '';
        showWelcomeMessage();
    }
}

// Show welcome message
function showWelcomeMessage() {
    const msgContainer = document.getElementById('chat-messages');
    const welcomeDiv = document.createElement('div');
    welcomeDiv.className = 'chat-msg bot';
    welcomeDiv.innerHTML = `
        Hi! 👋 I'm <strong>SnapBot AI</strong>, your Studio 94 assistant.<br><br>
        Ask me anything about our <strong>packages, prices, location, hours, availability,</strong> and payments!<br><br>
        💡 <em>I can help with bookings, schedules, and Studio 94 questions.</em>
    `;
    msgContainer.appendChild(welcomeDiv);

    // Save to history — raw text lang
    const history = loadChatHistory();
    history.push({
        type: 'bot',
        message: "Hi! 👋 I'm SnapBot AI, your Studio 94 assistant.\n\nAsk me anything about our packages, prices, location, hours, availability, and payments!\n\n💡 I can help with bookings, schedules, and Studio 94 questions.",
        timestamp: new Date().toISOString()
    });
    saveChatHistory(history);
}

// Render all messages from history
function renderChatHistory() {
    const msgContainer = document.getElementById('chat-messages');
    msgContainer.innerHTML = '';

    const history = loadChatHistory();
    if (history.length === 0) {
        showWelcomeMessage();
        return;
    }

    history.forEach(item => {
        const div = document.createElement('div');
        div.className = `chat-msg ${item.type}`;

        if (item.type === 'bot') {
            div.innerHTML = escapeAndFormat(item.message) || item.message;
        } else {
            div.textContent = item.message;
        }

        msgContainer.appendChild(div);
    });

    msgContainer.scrollTop = msgContainer.scrollHeight;
}

// ============================================================
// TOGGLE CHATBOT
// ============================================================
function toggleChatbot() {
    const widget = document.getElementById('chatbot-widget');
    if (widget) {
        if (widget.style.display === 'none' || widget.style.display === '') {
            widget.style.display = 'flex';
            widget.style.transform = 'translate3d(0, 0, 0)';
            xOffset = 0;
            yOffset = 0;

            renderChatHistory();

            setTimeout(() => {
                const input = document.getElementById('chat-input');
                if (input) input.focus();
            }, 100);
        } else {
            widget.style.display = 'none';
        }
    }
}

// ============================================================
// SEND CHAT MESSAGE
// ============================================================
async function sendChatMsg() {
    const inputEl = document.getElementById('chat-input');
    const msgContainer = document.getElementById('chat-messages');
    if (!inputEl || !msgContainer) return;

    const text = inputEl.value.trim();
    if (!text) return;

    // ===== ADD USER MESSAGE =====
    const userDiv = document.createElement('div');
    userDiv.className = 'chat-msg user';
    userDiv.textContent = text;
    msgContainer.appendChild(userDiv);

    // Save to history
    const history = loadChatHistory();
    history.push({
        type: 'user',
        message: text,
        timestamp: new Date().toISOString()
    });
    saveChatHistory(history);

    inputEl.value = '';
    msgContainer.scrollTop = msgContainer.scrollHeight;

    // ===== SHOW TYPING INDICATOR =====
    const typingDiv = document.createElement('div');
    typingDiv.className = 'chat-msg bot';
    typingDiv.innerHTML = `
        <div class="typing-indicator">
            <span></span>
            <span></span>
            <span></span>
        </div>
    `;
    typingDiv.id = 'typing-indicator';
    msgContainer.appendChild(typingDiv);
    msgContainer.scrollTop = msgContainer.scrollHeight;

    try {
        const apiUrl = 'pages/api/chatbot-ai.php';

        const response = await fetch(apiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message: text })
        });

        const data = await response.json();

        // ===== REMOVE TYPING INDICATOR =====
        const typing = document.getElementById('typing-indicator');
        if (typing) typing.remove();

        // ===== ADD BOT RESPONSE (formatted) =====
        const rawReply = data.reply || '😅 Sorry, I couldn\'t process your request. Please try again.';

        const botDiv = document.createElement('div');
        botDiv.className = 'chat-msg bot';
        botDiv.innerHTML = escapeAndFormat(rawReply);
        msgContainer.appendChild(botDiv);
        msgContainer.scrollTop = msgContainer.scrollHeight;

        // ===== SAVE RAW TEXT SA HISTORY (hindi HTML) =====
        const updatedHistory = loadChatHistory();
        updatedHistory.push({
            type: 'bot',
            message: rawReply,  // ← raw text lang
            timestamp: new Date().toISOString()
        });
        saveChatHistory(updatedHistory);

    } catch (error) {
        console.error('Chatbot Error:', error);

        const typing = document.getElementById('typing-indicator');
        if (typing) typing.remove();

        const botDiv = document.createElement('div');
        botDiv.className = 'chat-msg bot';
        botDiv.innerHTML = '📡 Connection error. Please check your internet and try again.';
        msgContainer.appendChild(botDiv);
        msgContainer.scrollTop = msgContainer.scrollHeight;
    }
}

// ============================================================
// QUICK REPLY
// ============================================================
function sendQuickReply(text) {
    document.getElementById('chat-input').value = text;
    sendChatMsg();
}

// ============================================================
// DRAGGABLE CHATBOT
// ============================================================
let isDragging = false;
let currentX, currentY, initialX, initialY;
let xOffset = 0, yOffset = 0;

document.addEventListener('DOMContentLoaded', function() {
    const header = document.getElementById('chat-header');
    const widget = document.getElementById('chatbot-widget');
    if (!header || !widget) return;

    header.addEventListener('mousedown', dragStart);
    document.addEventListener('mousemove', drag);
    document.addEventListener('mouseup', dragEnd);

    header.addEventListener('touchstart', touchStart, { passive: false });
    document.addEventListener('touchmove', touchMove, { passive: false });
    document.addEventListener('touchend', touchEnd, { passive: false });
});

function dragStart(e) {
    if (e.target.tagName === 'BUTTON') return;
    e.preventDefault();
    initialX = e.clientX - xOffset;
    initialY = e.clientY - yOffset;
    isDragging = true;
    document.getElementById('chat-header').style.cursor = 'grabbing';
}

function drag(e) {
    if (isDragging) {
        e.preventDefault();
        currentX = e.clientX - initialX;
        currentY = e.clientY - initialY;
        xOffset = currentX;
        yOffset = currentY;
        const widget = document.getElementById('chatbot-widget');
        widget.style.transform = `translate3d(${currentX}px, ${currentY}px, 0)`;
    }
}

function dragEnd(e) {
    initialX = currentX;
    initialY = currentY;
    isDragging = false;
    const header = document.getElementById('chat-header');
    if (header) header.style.cursor = 'grab';
}

function touchStart(e) {
    if (e.target.tagName === 'BUTTON') return;
    const touch = e.touches[0];
    initialX = touch.clientX - xOffset;
    initialY = touch.clientY - yOffset;
    isDragging = true;
}

function touchMove(e) {
    if (isDragging) {
        e.preventDefault();
        const touch = e.touches[0];
        currentX = touch.clientX - initialX;
        currentY = touch.clientY - initialY;
        xOffset = currentX;
        yOffset = currentY;
        const widget = document.getElementById('chatbot-widget');
        widget.style.transform = `translate3d(${currentX}px, ${currentY}px, 0)`;
    }
}

function touchEnd(e) {
    initialX = currentX;
    initialY = currentY;
    isDragging = false;
}
</script>