{{-- Floating Owner Virtual CFO AI Chat Widget --}}
<div id="ownerAiWidgetRoot" class="fixed bottom-4 right-4 z-50 flex flex-col items-end print:hidden">

    {{-- Chat Window (Initially Hidden) --}}
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
                <button type="button" onclick="clearOwnerAiChat()" title="Clear Conversation" class="w-7 h-7 rounded-md hover:bg-white/10 flex items-center justify-center text-indigo-100 hover:text-white transition-colors text-xs">
                    <i class="ri-delete-bin-line"></i>
                </button>
                <button type="button" onclick="toggleOwnerAiWidget(false)" title="Minimize" class="w-7 h-7 rounded-md hover:bg-white/10 flex items-center justify-center text-indigo-100 hover:text-white transition-colors text-sm">
                    <i class="ri-close-line"></i>
                </button>
            </div>
        </div>

        {{-- Messages Container --}}
        <div id="ownerAiMessages" class="flex-1 overflow-y-auto p-4 space-y-3 text-xs scrollbar">
            
            {{-- Welcome Bubble --}}
            <div class="flex items-start gap-2.5">
                <div class="w-6 h-6 rounded-md bg-indigo-100 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0 border border-indigo-200 dark:border-indigo-800">
                    <i class="ri-robot-2-line text-xs"></i>
                </div>
                <div class="bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700/60 rounded-xl p-3 text-slate-700 dark:text-slate-200 max-w-[85%] space-y-2">
                    <p class="font-medium text-slate-900 dark:text-white">
                        হ্যালো! আমি আপনার ব্যবসার <strong>ভার্চুয়াল সিএফও (Virtual CFO)</strong>।
                    </p>
                    <p class="text-2xs text-slate-600 dark:text-slate-300 leading-relaxed">
                        আমি আপনার সেলস, স্টক লেভেল, বাকি বকেয়া ও প্রফিট মার্জিনের সার্বিক উপাত্ত জানি। যেকোনো প্রশ্ন করতে পারেন:
                    </p>
                    
                    {{-- Quick Action Chips --}}
                    <div class="pt-1 flex flex-wrap gap-1.5" id="ownerAiQuickChips">
                        <button type="button" onclick="askOwnerAiPreset('আজকের মোট সেলস ও রেভিনিউ কত?')" class="text-2xs bg-white dark:bg-slate-700 hover:bg-indigo-50 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 px-2 py-1 rounded-md transition-all">
                            💰 আজকের সেলস
                        </button>
                        <button type="button" onclick="askOwnerAiPreset('কোন প্রোডাক্টটি সবচেয়ে বেশি বিক্রি হচ্ছে?')" class="text-2xs bg-white dark:bg-slate-700 hover:bg-indigo-50 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 px-2 py-1 rounded-md transition-all">
                            🔥 টপ সেলিং পণ্য
                        </button>
                        <button type="button" onclick="askOwnerAiPreset('কোন কোন আইটেমের স্টক কম রয়েছে?')" class="text-2xs bg-white dark:bg-slate-700 hover:bg-indigo-50 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 px-2 py-1 rounded-md transition-all">
                            ⚠️ লো-স্টক অ্যালার্ট
                        </button>
                        <button type="button" onclick="askOwnerAiPreset('গ্রাহকদের মোট বাকি বা ডিউ কত টাকা?')" class="text-2xs bg-white dark:bg-slate-700 hover:bg-indigo-50 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 px-2 py-1 rounded-md transition-all">
                            📑 কাস্টমার ব্যালেন্স
                        </button>
                    </div>
                </div>
            </div>

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

        {{-- Input Form Footer --}}
        <form id="ownerAiForm" onsubmit="submitOwnerAiPrompt(event)" class="p-2.5 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-200 dark:border-slate-800 flex items-center gap-2 shrink-0">
            <input type="text" id="ownerAiInput" placeholder="ব্যবসা বা সেলস নিয়ে প্রশ্ন করুন..." autocomplete="off" class="flex-1 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 transition-all">
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
    function toggleOwnerAiWidget(show) {
        const win = document.getElementById('ownerAiWindow');
        const trigger = document.getElementById('ownerAiTriggerBtn');
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
    }

    function scrollOwnerAiBottom() {
        const box = document.getElementById('ownerAiMessages');
        if (box) {
            box.scrollTop = box.scrollHeight;
        }
    }

    function clearOwnerAiChat() {
        const box = document.getElementById('ownerAiMessages');
        if (!box) return;
        box.innerHTML = `
            <div class="flex items-start gap-2.5">
                <div class="w-6 h-6 rounded-md bg-indigo-100 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0 border border-indigo-200 dark:border-indigo-800">
                    <i class="ri-robot-2-line text-xs"></i>
                </div>
                <div class="bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700/60 rounded-xl p-3 text-slate-700 dark:text-slate-200 max-w-[85%] space-y-2">
                    <p class="font-medium text-slate-900 dark:text-white">নতুন চ্যাট শুরু হয়েছে।</p>
                    <p class="text-2xs text-slate-600 dark:text-slate-300">আজকের সেলস বা স্টক নিয়ে প্রশ্ন করুন:</p>
                    <div class="pt-1 flex flex-wrap gap-1.5">
                        <button type="button" onclick="askOwnerAiPreset('আজকের মোট সেলস ও রেভিনিউ কত?')" class="text-2xs bg-white dark:bg-slate-700 hover:bg-indigo-50 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 px-2 py-1 rounded-md transition-all">💰 আজকের সেলস</button>
                        <button type="button" onclick="askOwnerAiPreset('কোন কোন আইটেমের স্টক কম রয়েছে?')" class="text-2xs bg-white dark:bg-slate-700 hover:bg-indigo-50 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-600 px-2 py-1 rounded-md transition-all">⚠️ লো-স্টক</button>
                    </div>
                </div>
            </div>
        `;
    }

    function askOwnerAiPreset(text) {
        const input = document.getElementById('ownerAiInput');
        if (input) {
            input.value = text;
            submitOwnerAiPrompt(new Event('submit'));
        }
    }

    async function submitOwnerAiPrompt(e) {
        if (e && e.preventDefault) e.preventDefault();
        const input = document.getElementById('ownerAiInput');
        const question = input ? input.value.trim() : '';
        if (!question) return;

        const submitBtn = document.getElementById('ownerAiSubmitBtn');
        const indicator = document.getElementById('ownerAiTypingIndicator');
        const box = document.getElementById('ownerAiMessages');

        // Append User Bubble
        const userHtml = `
            <div class="flex justify-end">
                <div class="bg-indigo-600 text-white rounded-xl rounded-tr-sm px-3 py-2 max-w-[85%] shadow-sm">
                    <p class="text-xs leading-relaxed whitespace-pre-wrap">${escapeHtml(question)}</p>
                </div>
            </div>
        `;
        box.insertAdjacentHTML('beforeend', userHtml);
        input.value = '';
        input.disabled = true;
        if (submitBtn) submitBtn.disabled = true;
        if (indicator) indicator.classList.remove('hidden');
        scrollOwnerAiBottom();

        try {
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const res = await fetch("{{ route('owner-ai.ask') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token || ''
                },
                body: JSON.stringify({ question: question })
            });

            const data = await res.json();
            if (data.status === 'success') {
                appendOwnerAiBotBubble(data.answer, data.execution_time);
            } else {
                appendOwnerAiBotBubble('দুঃখিত, তথ্য লোড করতে সমস্যা হয়েছে: ' + (data.message || 'Unknown error'));
            }
        } catch (err) {
            appendOwnerAiBotBubble('সার্ভারে যোগাযোগ করা যায়নি। আপনার ইন্টারনেট কানেকশন চেক করুন।');
        } finally {
            input.disabled = false;
            if (submitBtn) submitBtn.disabled = false;
            if (indicator) indicator.classList.add('hidden');
            input.focus();
            scrollOwnerAiBottom();
        }
    }

    function appendOwnerAiBotBubble(rawAnswer, execTime) {
        const box = document.getElementById('ownerAiMessages');
        if (!box) return;

        let formatted = formatSimpleMarkdown(rawAnswer);
        const timeBadge = execTime ? `<span class="text-3xs text-slate-400 font-mono">⚡ ${execTime}</span>` : '';

        const botHtml = `
            <div class="flex items-start gap-2.5 animate-in fade-in">
                <div class="w-6 h-6 rounded-md bg-indigo-100 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center shrink-0 border border-indigo-200 dark:border-indigo-800">
                    <i class="ri-sparkling-fill text-xs"></i>
                </div>
                <div class="bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700/60 rounded-xl p-3 text-slate-700 dark:text-slate-200 max-w-[85%] space-y-1.5">
                    <div class="prose prose-xs dark:prose-invert max-w-none text-xs leading-relaxed">
                        ${formatted}
                    </div>
                    <div class="flex justify-end pt-1">
                        ${timeBadge}
                    </div>
                </div>
            </div>
        `;
        box.insertAdjacentHTML('beforeend', botHtml);
    }

    function formatSimpleMarkdown(text) {
        if (!text) return '';
        let s = escapeHtml(text);
        // Bold: **text**
        s = s.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        // Bullet lines
        s = s.replace(/^- (.*$)/gim, '<li class="ml-3 list-disc">$1</li>');
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
</script>
