{{-- Floating Owner Virtual CFO AI Chat Widget --}}
<div id="ownerAiWidgetRoot" class="fixed bottom-4 right-4 z-50 flex flex-col items-end print:hidden">

    {{-- Chat Window (Initially Hidden, restored via localStorage if left open) --}}
    <div id="ownerAiWindow" class="hidden w-[92vw] sm:w-[420px] h-[540px] max-h-[80vh] bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-2xl flex flex-col overflow-hidden mb-3 transition-all duration-200 animate-in fade-in slide-in-from-bottom-4">
        
        {{-- Window Header --}}
        <div class="px-4 py-3 bg-gradient-to-r from-indigo-600 via-indigo-700 to-violet-800 text-white flex items-center justify-between shrink-0 shadow-sm">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-white/10 backdrop-blur-md flex items-center justify-center text-amber-300 border border-white/20">
                    <i class="ri-sparkling-fill text-base"></i>
                </div>
                <div>
                    <h3 class="text-xs font-bold leading-tight flex items-center gap-1.5">
                        Virtual CFO & Intelligence
                        <span class="text-3xs px-1.5 py-0.5 rounded-full bg-white/20 text-white font-medium">BO-2</span>
                    </h3>
                    <p class="text-2xs text-indigo-100 font-normal">রিয়েল-টাইম কমার্স ও অ্যানালিটিক্স সহকারী</p>
                </div>
            </div>
            <div class="flex items-center gap-1">
                <button type="button" onclick="clearOwnerAiChat()" title="চ্যাট হিস্ট্রি ক্লিয়ার করুন" class="w-7 h-7 rounded-md hover:bg-white/10 flex items-center justify-center text-indigo-100 hover:text-white transition-colors text-xs">
                    <i class="ri-delete-bin-line"></i>
                </button>
                <button type="button" onclick="toggleOwnerAiWidget(false)" title="Minimize" class="w-7 h-7 rounded-md hover:bg-white/10 flex items-center justify-center text-indigo-100 hover:text-white transition-colors text-sm">
                    <i class="ri-close-line"></i>
                </button>
            </div>
        </div>

        {{-- Messages Container (Populated dynamically and persisted via localStorage) --}}
        <div id="ownerAiMessages" class="flex-1 overflow-y-auto p-4 space-y-3 text-xs scrollbar">
            {{-- Dynamically rendered via renderAllOwnerAiMessages() --}}
        </div>

        {{-- Typing Indicator (Hidden by default) --}}
        <div id="ownerAiTypingIndicator" class="hidden px-4 py-2 text-2xs text-slate-500 dark:text-slate-400 flex items-center gap-2 bg-slate-50/50 dark:bg-slate-900/50 border-t border-slate-100 dark:border-slate-800">
            <span class="inline-flex items-center gap-1">
                <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 animate-bounce"></span>
                <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 animate-bounce" style="animation-delay: 150ms;"></span>
                <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 animate-bounce" style="animation-delay: 300ms;"></span>
            </span>
            <span>উপাত্ত বিশ্লেষণ করা হচ্ছে...</span>
        </div>

        {{-- Input Form Footer (Strictly prevents traditional page reload) --}}
        <form id="ownerAiForm" onsubmit="event.preventDefault(); submitOwnerAiPrompt(event); return false;" class="p-2.5 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-200 dark:border-slate-800 flex items-center gap-2 shrink-0">
            <input type="text" id="ownerAiInput" placeholder="ব্যবসা, সেলস বা মেসেজ খরচ নিয়ে প্রশ্ন করুন..." autocomplete="off" class="flex-1 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 transition-all">
            <button type="submit" id="ownerAiSubmitBtn" class="w-8 h-8 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white flex items-center justify-center shrink-0 shadow-sm transition-all disabled:opacity-50">
                <i class="ri-send-plane-2-fill text-xs"></i>
            </button>
        </form>

    </div>

    {{-- Floating Trigger Pill Button --}}
    <button type="button" id="ownerAiTriggerBtn" onclick="toggleOwnerAiWidget(true)" class="group flex items-center gap-2 px-3.5 py-2.5 rounded-full bg-gradient-to-r from-indigo-600 via-indigo-700 to-violet-700 text-white shadow-lg hover:shadow-xl hover:scale-105 active:scale-95 transition-all duration-200 border border-white/20">
        <span class="relative flex h-2.5 w-2.5">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-amber-400"></span>
        </span>
        <i class="ri-sparkling-fill text-amber-300 text-sm"></i>
        <span class="text-xs font-semibold tracking-wide">Ask Virtual CFO</span>
    </button>

</div>

<script>
    // Workspace-isolated storage keys to prevent cross-account leak
    const OWNER_AI_WS_ID = {{ (int)(Auth::user()?->workspace_id ?: 1) }};
    const OWNER_AI_STORAGE_KEY = 'owner_ai_chat_history_v2_ws_' + OWNER_AI_WS_ID;
    const OWNER_AI_OPEN_KEY = 'owner_ai_chat_open_ws_' + OWNER_AI_WS_ID;

    // In-memory messages array
    let ownerAiChatMessages = [];

    // Base Welcome Template HTML
    const OWNER_AI_WELCOME_HTML = `
        <div class="flex items-start gap-2.5">
            <div class="w-6 h-6 rounded-md bg-indigo-100 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0 border border-indigo-200 dark:border-indigo-800">
                <i class="ri-robot-2-line text-xs"></i>
            </div>
            <div class="bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700/60 rounded-xl p-3 text-slate-700 dark:text-slate-200 max-w-[85%] space-y-2">
                <p class="font-medium text-slate-900 dark:text-white">
                    হ্যালো! আমি আপনার ব্যবসার <strong>ভার্চুয়াল সিএফও (Virtual CFO)</strong>।
                </p>
                <p class="text-2xs text-slate-600 dark:text-slate-300 leading-relaxed">
                    আমি আপনার সেলস, স্টক, লাভ ও মার্জিন এবং মোট মেসেজিং কোটা ও ওয়ালেটের রিয়েল-টাইম উপাত্ত জানি। যেকোনো প্রশ্ন করতে পারেন:
                </p>
                
                {{-- Quick Action Chips --}}
                <div class="pt-1 flex flex-wrap gap-1.5" id="ownerAiQuickChips">
                    <button type="button" onclick="askOwnerAiPreset('আমার কয়টা মেসেজ গেছে এবং কত টাকা খরচ হয়েছে?')" class="text-2xs bg-white dark:bg-slate-700 hover:bg-indigo-50 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 px-2 py-1 rounded-md transition-all font-medium">
                        💬 মেসেজ ও খরচ কত?
                    </button>
                    <button type="button" onclick="askOwnerAiPreset('আজকের মোট সেলস ও রেভিনিউ কত?')" class="text-2xs bg-white dark:bg-slate-700 hover:bg-indigo-50 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 px-2 py-1 rounded-md transition-all">
                        💰 আজকের সেলস
                    </button>
                    <button type="button" onclick="askOwnerAiPreset('কোন প্রোডাক্টটি সবচেয়ে বেশি বিক্রি হচ্ছে?')" class="text-2xs bg-white dark:bg-slate-700 hover:bg-indigo-50 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 px-2 py-1 rounded-md transition-all">
                        🔥 টপ সেলিং পণ্য
                    </button>
                    <button type="button" onclick="askOwnerAiPreset('কোন কোন আইটেমের স্টক কম রয়েছে?')" class="text-2xs bg-white dark:bg-slate-700 hover:bg-indigo-50 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 px-2 py-1 rounded-md transition-all">
                        ⚠️ লো-স্টক ইনভেন্টরি
                    </button>
                    <button type="button" onclick="askOwnerAiPreset('চলতি মাসের নিট লাভ বা মার্জিন কত?')" class="text-2xs bg-white dark:bg-slate-700 hover:bg-indigo-50 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 px-2 py-1 rounded-md transition-all font-medium">
                        📈 লাভ ও মার্জিন
                    </button>
                </div>
            </div>
        </div>
    `;

    // Render all messages into DOM and retain scroll position
    function renderAllOwnerAiMessages() {
        const box = document.getElementById('ownerAiMessages');
        if (!box) return;

        let html = OWNER_AI_WELCOME_HTML;

        for (const msg of ownerAiChatMessages) {
            if (msg.sender === 'user') {
                html += `
                    <div class="flex justify-end animate-in fade-in">
                        <div class="bg-indigo-600 text-white rounded-xl rounded-tr-sm px-3 py-2 max-w-[85%] shadow-sm">
                            <p class="text-xs leading-relaxed whitespace-pre-wrap">${escapeHtml(msg.text)}</p>
                        </div>
                    </div>
                `;
            } else if (msg.sender === 'bot') {
                const formatted = formatSimpleMarkdown(msg.text);
                const timeBadge = msg.execTime ? `<span class="text-3xs text-slate-400 font-mono">⚡ ${msg.execTime}</span>` : '';
                html += `
                    <div class="flex items-start gap-2.5 animate-in fade-in">
                        <div class="w-6 h-6 rounded-md bg-indigo-100 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0 border border-indigo-200 dark:border-indigo-800">
                            <i class="ri-sparkling-fill text-xs"></i>
                        </div>
                        <div class="bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700/60 rounded-xl p-3 text-slate-700 dark:text-slate-200 max-w-[85%] space-y-1.5 shadow-sm">
                            <div class="prose prose-xs dark:prose-invert max-w-none text-xs leading-relaxed">
                                ${formatted}
                            </div>
                            <div class="flex justify-end pt-1">
                                ${timeBadge}
                            </div>
                        </div>
                    </div>
                `;
            }
        }

        box.innerHTML = html;
        scrollOwnerAiBottom();
    }

    // Load history and open state from localStorage on page load
    function initOwnerAiWidget() {
        try {
            const saved = localStorage.getItem(OWNER_AI_STORAGE_KEY);
            if (saved) {
                ownerAiChatMessages = JSON.parse(saved);
                if (!Array.isArray(ownerAiChatMessages)) ownerAiChatMessages = [];
            }
        } catch (e) {
            console.warn('[VirtualCFO] Failed to load chat history:', e);
            ownerAiChatMessages = [];
        }

        renderAllOwnerAiMessages();

        try {
            const isOpen = localStorage.getItem(OWNER_AI_OPEN_KEY);
            if (isOpen === 'true') {
                toggleOwnerAiWidget(true, false);
            }
        } catch (e) {}
    }

    // Persist messages array into localStorage
    function saveOwnerAiChatHistory() {
        try {
            localStorage.setItem(OWNER_AI_STORAGE_KEY, JSON.stringify(ownerAiChatMessages));
        } catch (e) {
            console.warn('[VirtualCFO] Failed to save chat history:', e);
        }
    }

    // Toggle widget window visibility with optional state persistence
    function toggleOwnerAiWidget(show, persist = true) {
        const win = document.getElementById('ownerAiWindow');
        const trigger = document.getElementById('ownerAiTriggerBtn');
        if (!win || !trigger) return;

        if (show) {
            win.classList.remove('hidden');
            trigger.classList.add('hidden');
            setTimeout(() => {
                document.getElementById('ownerAiInput')?.focus();
                scrollOwnerAiBottom();
            }, 100);
        } else {
            win.classList.add('hidden');
            trigger.classList.remove('hidden');
        }

        if (persist) {
            try {
                localStorage.setItem(OWNER_AI_OPEN_KEY, show ? 'true' : 'false');
            } catch (e) {}
        }
    }

    function scrollOwnerAiBottom() {
        const box = document.getElementById('ownerAiMessages');
        if (box) {
            box.scrollTop = box.scrollHeight;
        }
    }

    // Clear conversation only on user explicit action
    function clearOwnerAiChat() {
        ownerAiChatMessages = [];
        try {
            localStorage.removeItem(OWNER_AI_STORAGE_KEY);
        } catch (e) {}
        renderAllOwnerAiMessages();
    }

    function askOwnerAiPreset(text) {
        toggleOwnerAiWidget(true);
        const input = document.getElementById('ownerAiInput');
        if (input) {
            input.value = text;
            submitOwnerAiPrompt(null);
        }
    }

    async function submitOwnerAiPrompt(e) {
        if (e) {
            if (typeof e.preventDefault === 'function') e.preventDefault();
            if (typeof e.stopPropagation === 'function') e.stopPropagation();
        }

        const input = document.getElementById('ownerAiInput');
        const question = input ? input.value.trim() : '';
        if (!question) return;

        const submitBtn = document.getElementById('ownerAiSubmitBtn');
        const indicator = document.getElementById('ownerAiTypingIndicator');

        // Append User Message to State & Persist
        ownerAiChatMessages.push({
            sender: 'user',
            text: question,
            time: new Date().toLocaleTimeString()
        });
        saveOwnerAiChatHistory();
        renderAllOwnerAiMessages();

        input.value = '';
        input.disabled = true;
        if (submitBtn) submitBtn.disabled = true;
        if (indicator) indicator.classList.remove('hidden');
        scrollOwnerAiBottom();

        // Prepare recent conversation history for multi-turn context (up to last 6 messages prior to current)
        const recentHistory = ownerAiChatMessages.slice(0, -1).slice(-6).map(m => ({
            role: m.sender === 'user' ? 'user' : 'assistant',
            text: m.text
        }));

        try {
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const res = await fetch("{{ route('owner-ai.ask') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token || ''
                },
                body: JSON.stringify({ 
                    question: question,
                    history: recentHistory
                })
            });

            let data = null;
            try {
                data = await res.json();
            } catch (jsonErr) {}

            if (res.ok && data && data.status === 'success' && data.answer) {
                ownerAiChatMessages.push({
                    sender: 'bot',
                    text: data.answer,
                    execTime: data.execution_time
                });
                saveOwnerAiChatHistory();
                renderAllOwnerAiMessages();
            } else {
                const errMsg = (data && (data.message || data.answer)) 
                    ? (data.message || data.answer)
                    : (res.status === 419 ? 'সেশন শেষ হয়ে গেছে। অনুগ্রহ করে পেজটি একবার রিলোড করুন।' : 'সার্ভার থেকে উত্তর লোড করা সম্ভব হয়নি (HTTP ' + res.status + ')।');
                ownerAiChatMessages.push({
                    sender: 'bot',
                    text: '⚠️ ' + errMsg
                });
                saveOwnerAiChatHistory();
                renderAllOwnerAiMessages();
            }
        } catch (err) {
            console.error('[VirtualCFO] Query execution error:', err);
            ownerAiChatMessages.push({
                sender: 'bot',
                text: 'সার্ভারে যোগাযোগ করা যায়নি। আপনার ইন্টারনেট কানেকশন বা সেশন চেক করুন।'
            });
            saveOwnerAiChatHistory();
            renderAllOwnerAiMessages();
        } finally {
            input.disabled = false;
            if (submitBtn) submitBtn.disabled = false;
            if (indicator) indicator.classList.add('hidden');
            input.focus();
            scrollOwnerAiBottom();
        }
    }

    function formatSimpleMarkdown(text) {
        if (!text) return '';
        let s = escapeHtml(text);
        // Bold: **text**
        s = s.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        // Bullet lines
        s = s.replace(/^- (.*$)/gim, '<li class="ml-3 list-disc">$1</li>');
        s = s.replace(/^• (.*$)/gim, '<li class="ml-5 list-circle">$1</li>');
        // Numbered lines
        s = s.replace(/^(\d+)\. (.*$)/gim, '<li class="ml-3 list-decimal">$2</li>');
        // Italic: *text*
        s = s.replace(/\*(.*?)\*/g, '<em>$1</em>');
        // Newlines
        s = s.replace(/\n\n/g, '<br><br>');
        s = s.replace(/\n/g, '<br>');
        return s;
    }

    function escapeHtml(string) {
        const entityMap = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        };
        return String(string).replace(/[&<>"']/g, function (s) {
            return entityMap[s];
        });
    }

    // Initialize widget on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initOwnerAiWidget);
    } else {
        initOwnerAiWidget();
    }
</script>
