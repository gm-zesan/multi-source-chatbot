(function () {
    'use strict';

    var scriptTag = document.currentScript || document.querySelector('script[data-account-id]');
    var accountId = scriptTag ? scriptTag.getAttribute('data-account-id') : null;
    var apiEndpoint = scriptTag && scriptTag.getAttribute('data-endpoint') ? scriptTag.getAttribute('data-endpoint') : '/widget/chat';
    var widgetTitle = scriptTag && scriptTag.getAttribute('data-title') ? scriptTag.getAttribute('data-title') : 'Customer Support';
    var defaultGreeting = '\u0986\u09b8\u09b8\u09be\u09b2\u09be\u09ae\u09c1 \u0986\u09b2\u09be\u0987\u0995\u09c1\u09ae! \u0986\u09aa\u09a8\u09be\u0995\u09c7 \u0995\u09c0\u09ad\u09be\u09ac\u09c7 \u09b8\u09be\u09b9\u09be\u09af\u09cd\u09af \u0995\u09b0\u09a4\u09c7 \u09aa\u09be\u09b0\u09bf?';
    var greetingText = scriptTag && scriptTag.getAttribute('data-greeting') ? scriptTag.getAttribute('data-greeting') : defaultGreeting;

    var visitorId = localStorage.getItem('agy_widget_visitor_id');
    if (!visitorId) {
        visitorId = 'vis_' + Math.random().toString(36).substring(2, 12) + Date.now().toString(36);
        localStorage.setItem('agy_widget_visitor_id', visitorId);
    }

    // Styles
    var style = document.createElement('style');
    style.innerHTML = `
        #agy-chat-bubble {
            position: fixed;
            bottom: 24px;
            right: 24px;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: #2563eb;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.4);
            z-index: 999999;
            transition: transform 0.2s ease;
        }
        #agy-chat-bubble:hover {
            transform: scale(1.06);
        }
        #agy-chat-window {
            position: fixed;
            bottom: 92px;
            right: 24px;
            width: 360px;
            height: 520px;
            max-width: calc(100vw - 32px);
            max-height: calc(100vh - 120px);
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
            display: none;
            flex-direction: column;
            overflow: hidden;
            z-index: 999999;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        #agy-chat-header {
            background: #2563eb;
            color: #ffffff;
            padding: 14px 16px;
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        #agy-chat-messages {
            flex: 1;
            padding: 16px;
            overflow-y: auto;
            background: #f8fafc;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .agy-msg {
            max-width: 80%;
            padding: 10px 14px;
            border-radius: 14px;
            font-size: 14px;
            line-height: 1.4;
            word-wrap: break-word;
        }
        .agy-msg-inbound {
            align-self: flex-end;
            background: #2563eb;
            color: #ffffff;
            border-bottom-right-radius: 2px;
        }
        .agy-msg-outbound {
            align-self: flex-start;
            background: #e2e8f0;
            color: #1e293b;
            border-bottom-left-radius: 2px;
        }
        #agy-chat-input-bar {
            display: flex;
            border-top: 1px solid #e2e8f0;
            background: #ffffff;
            padding: 8px 12px;
        }
        #agy-chat-input {
            flex: 1;
            border: 1px solid #cbd5e1;
            border-radius: 20px;
            padding: 8px 14px;
            font-size: 14px;
            outline: none;
        }
        #agy-chat-send {
            background: none;
            border: none;
            color: #2563eb;
            font-weight: 600;
            cursor: pointer;
            padding: 0 10px;
        }
    `;
    document.head.appendChild(style);

    // Bubble
    var bubble = document.createElement('div');
    bubble.id = 'agy-chat-bubble';
    bubble.innerHTML = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>';
    document.body.appendChild(bubble);

    // Chat Window
    var win = document.createElement('div');
    win.id = 'agy-chat-window';
    win.innerHTML = `
        <div id="agy-chat-header">
            <span>${widgetTitle}</span>
            <span id="agy-chat-close" style="cursor: pointer; font-size: 18px;">&times;</span>
        </div>
        <div id="agy-chat-messages">
            <div class="agy-msg agy-msg-outbound">${greetingText}</div>
        </div>
        <div id="agy-chat-input-bar">
            <input type="text" id="agy-chat-input" placeholder="Type a message..." autocomplete="off" />
            <button id="agy-chat-send">Send</button>
        </div>
    `;
    document.body.appendChild(win);

    var isOpen = false;
    bubble.addEventListener('click', function () {
        isOpen = !isOpen;
        win.style.display = isOpen ? 'flex' : 'none';
    });

    document.getElementById('agy-chat-close').addEventListener('click', function () {
        isOpen = false;
        win.style.display = 'none';
    });

    var inputEl = document.getElementById('agy-chat-input');
    var sendBtn = document.getElementById('agy-chat-send');
    var messagesEl = document.getElementById('agy-chat-messages');

    function appendMessage(text, isUser) {
        var msg = document.createElement('div');
        msg.className = 'agy-msg ' + (isUser ? 'agy-msg-inbound' : 'agy-msg-outbound');
        msg.textContent = text;
        messagesEl.appendChild(msg);
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function sendMessage() {
        var text = inputEl.value.trim();
        if (!text) return;
        inputEl.value = '';
        appendMessage(text, true);

        // Show typing indicator
        var typingIndicator = document.createElement('div');
        typingIndicator.className = 'agy-msg agy-msg-outbound';
        typingIndicator.id = 'agy-typing-indicator';
        typingIndicator.textContent = '...';
        messagesEl.appendChild(typingIndicator);
        messagesEl.scrollTop = messagesEl.scrollHeight;

        fetch(apiEndpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({
                channel: 'website',
                channel_account_id: accountId,
                visitor_id: visitorId,
                text: text
            })
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            var typing = document.getElementById('agy-typing-indicator');
            if (typing) typing.remove();
            if (data && data.reply) {
                appendMessage(data.reply, false);
            } else {
                appendMessage('\u09a7\u09a8\u09cd\u09af\u09ac\u09be\u09a6! \u0986\u09aa\u09a8\u09be\u09b0 \u09ac\u09be\u09b0\u09cd\u09a4\u09be\u099f\u09bf \u0986\u09ae\u09b0\u09be \u09aa\u09c7\u09df\u09c7\u099b\u09bf\u0964', false);
            }
        })
        .catch(function (err) {
            console.error('Widget send error:', err);
            var typing = document.getElementById('agy-typing-indicator');
            if (typing) typing.remove();
            appendMessage('\u09b8\u0982\u09af\u09cb\u0997 \u09b8\u09cd\u09a5\u09be\u09aa\u09a8 \u0995\u09b0\u09a4\u09c7 \u09b8\u09ae\u09b8\u09cd\u09af\u09be \u09b9\u099a\u09cd\u099b\u09c7\u0964 \u0995\u09bf\u099b\u09c1\u0995\u09cd\u09b7\u09a3 \u09aa\u09b0 \u0986\u09ac\u09be\u09b0 \u099a\u09c7\u09b7\u09cd\u099f\u09be \u0995\u09b0\u09c1\u09a8\u0964', false);
        });
    }

    sendBtn.addEventListener('click', sendMessage);
    inputEl.addEventListener('keypress', function (e) {
        if (e.key === 'Enter') sendMessage();
    });
})();
