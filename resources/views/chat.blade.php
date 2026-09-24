<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Multi-Source Chatbot</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.2.0/fonts/remixicon.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* ── CSS Variables (Theme) ── */
        :root {
            --bg: #f0f2f5;
            --surface: #ffffff;
            --surface-hover: #f8fafc;
            --bubble-user: #2563eb;
            --bubble-user-text: #ffffff;
            --bubble-bot: #ffffff;
            --bubble-bot-text: #0f1724;
            --text-primary: #0f1724;
            --text-secondary: #64748b;
            --text-muted: #94a3b8;
            --border: #e2e8f0;
            --border-light: #f1f5f9;
            --accent: #2563eb;
            --accent-hover: #1d4ed8;
            --accent-light: #eff6ff;
            --green: #059669;
            --green-light: #ecfdf5;
            --amber: #d97706;
            --amber-light: #fffbeb;
            --gray: #6b7280;
            --gray-light: #f3f4f6;
            --danger: #dc2626;
            --danger-light: #fef2f2;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.04);
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.06), 0 2px 4px -2px rgba(0,0,0,0.04);
            --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.08), 0 4px 6px -4px rgba(0,0,0,0.04);
            --shadow-xl: 0 20px 25px -5px rgba(0,0,0,0.08), 0 8px 10px -6px rgba(0,0,0,0.04);
            --radius-sm: 6px;
            --radius-md: 10px;
            --radius-lg: 16px;
            --radius-xl: 20px;
            --radius-full: 9999px;
            --header-h: 64px;
            --input-h: 72px;
        }

        *{box-sizing:border-box;margin:0;padding:0}
        body{
            font-family:'Inter',system-ui,-apple-system,sans-serif;
            background:var(--bg);color:var(--text-primary);
            height:100vh;display:flex;flex-direction:column;
            overflow:hidden;
        }

        /* ── Scrollbar ── */
        ::-webkit-scrollbar{width:4px}
        ::-webkit-scrollbar-track{background:transparent}
        ::-webkit-scrollbar-thumb{background:var(--border);border-radius:var(--radius-full)}
        ::-webkit-scrollbar-thumb:hover{background:var(--text-muted)}

        /* ── Header ── */
        .header{
            height:var(--header-h);
            display:flex;align-items:center;justify-content:space-between;
            padding:0 24px;
            background:var(--surface);
            border-bottom:1px solid var(--border);
            flex-shrink:0;
            z-index:10;
        }
        .header-left{display:flex;align-items:center;gap:14px}
        .logo{
            width:36px;height:36px;border-radius:var(--radius-md);
            display:flex;align-items:center;justify-content:center;
            background:linear-gradient(135deg,#2563eb,#7c3aed);
            color:white;font-weight:700;font-size:15px;
            flex-shrink:0;
        }
        .header h1{font-size:16px;font-weight:700;letter-spacing:-0.3px}
        .header p{font-size:12px;color:var(--text-secondary);margin-top:1px}
        .header-badge{
            font-size:11px;font-weight:600;padding:4px 10px;
            border-radius:var(--radius-full);
            background:var(--accent-light);color:var(--accent);
        }

        /* ── Chat Container ── */
        .chat-container{
            flex:1;overflow-y:auto;overflow-x:hidden;
            padding:20px 16px 16px;
            display:flex;flex-direction:column;
            gap:6px;
            scroll-behavior:smooth;
        }
        .chat-container:empty::after{
            content:'No messages yet. Type a query below to get started.';
            display:flex;align-items:center;justify-content:center;
            height:100%;color:var(--text-muted);font-size:14px;
            text-align:center;padding:40px;
        }

        /* ── Message Bubbles ── */
        .msg-row{display:flex;margin-bottom:4px;animation:fadeIn 0.25s ease-out}
        .msg-row.user{justify-content:flex-end}
        .msg-row.bot{justify-content:flex-start}
        .msg-bubble{
            max-width:82%;padding:12px 16px;border-radius:var(--radius-lg);
            font-size:14px;line-height:1.55;word-wrap:break-word;
            box-shadow:var(--shadow-sm);position:relative;
        }
        .msg-row.user .msg-bubble{
            background:var(--bubble-user);color:var(--bubble-user-text);
            border-bottom-right-radius:4px;
        }
        .msg-row.bot .msg-bubble{
            background:var(--bubble-bot);color:var(--bubble-bot-text);
            border-bottom-left-radius:4px;
            border:1px solid var(--border);
        }
        .msg-label{
            font-size:11px;font-weight:600;margin-bottom:4px;
            opacity:0.7;letter-spacing:0.3px;
        }
        .msg-row.user .msg-label{color:var(--bubble-user-text);text-align:right}
        .msg-row.bot .msg-label{color:var(--text-secondary)}
        .msg-time{
            font-size:10px;color:var(--text-muted);margin-top:4px;
            display:block;opacity:0.6;
        }
        .msg-row.user .msg-time{text-align:right;color:rgba(255,255,255,0.6)}

        /* ── Confidence Badge ── */
        .confidence-badge{
            display:inline-flex;align-items:center;gap:4px;
            font-size:11px;font-weight:600;
            padding:3px 8px;border-radius:var(--radius-full);
            margin-bottom:8px;letter-spacing:0.2px;
        }
        .confidence-badge.high{background:var(--green-light);color:var(--green)}
        .confidence-badge.medium{background:var(--amber-light);color:var(--amber)}
        .confidence-badge.low{background:var(--gray-light);color:var(--gray)}
        .confidence-dot{
            width:6px;height:6px;border-radius:50%;display:inline-block;
        }
        .confidence-badge.high .confidence-dot{background:var(--green)}
        .confidence-badge.medium .confidence-dot{background:var(--amber)}
        .confidence-badge.low .confidence-dot{background:var(--gray)}

        /* ── Table Styling ── */
        .table-wrap{
            overflow-x:auto;margin:4px 0 2px;
            border-radius:var(--radius-md);
            border:1px solid var(--border-light);
        }
        table.bot-table{
            width:100%;border-collapse:collapse;
            font-size:13px;min-width:400px;
        }
        table.bot-table thead{background:var(--gray-light)}
        table.bot-table th{
            padding:9px 12px;font-weight:600;color:var(--text-secondary);
            text-align:left;font-size:12px;text-transform:uppercase;
            letter-spacing:0.5px;white-space:nowrap;
        }
        table.bot-table td{
            padding:8px 12px;border-bottom:1px solid var(--border-light);
            color:var(--text-primary);white-space:nowrap;
        }
        table.bot-table tbody tr{transition:background 0.15s}
        table.bot-table tbody tr:hover{background:var(--surface-hover)}
        table.bot-table tbody tr:last-child td{border-bottom:none}
        .row-count{
            font-size:11px;color:var(--text-muted);margin-top:6px;
            display:flex;align-items:center;gap:4px;
        }

        /* ── Typing Indicator ── */
        .typing-indicator{
            display:flex;align-items:center;gap:4px;padding:4px 0;
        }
        .typing-indicator span{
            width:7px;height:7px;border-radius:50%;
            background:var(--text-muted);display:inline-block;
            animation:typing 1.2s infinite;
        }
        .typing-indicator span:nth-child(2){animation-delay:0.2s}
        .typing-indicator span:nth-child(3){animation-delay:0.4s}
        @keyframes typing{
            0%,60%,100%{opacity:0.3;transform:scale(0.8)}
            30%{opacity:1;transform:scale(1)}
        }

        /* ── Visual Data Chart & Export Styling ── */
        .analytics-chart-card {
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 12px;
            margin-top: 10px;
            box-shadow: var(--shadow-sm);
        }
        .analytics-chart-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
            padding-bottom: 6px;
            border-bottom: 1px solid var(--border-light);
        }
        .analytics-chart-title {
            font-size: 12px;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .chart-toggle-group {
            display: flex;
            gap: 3px;
            background: var(--gray-light);
            padding: 2px;
            border-radius: 6px;
        }
        .chart-toggle-btn {
            font-size: 11px;
            padding: 2px 7px;
            border-radius: 4px;
            border: none;
            background: transparent;
            color: var(--text-secondary);
            cursor: pointer;
            font-weight: 600;
            transition: all 0.15s ease;
        }
        .chart-toggle-btn.active {
            background: #ffffff;
            color: var(--accent);
            box-shadow: 0 1px 2px rgba(0,0,0,0.08);
        }
        .chart-canvas-wrapper {
            position: relative;
            height: 160px;
            width: 100%;
        }
        .export-actions-bar {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 6px;
            margin-top: 8px;
            padding-top: 6px;
            border-top: 1px dashed var(--border);
        }
        .export-btn {
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 4px;
            border: 1px solid var(--border);
            background: var(--surface);
            color: var(--text-secondary);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-weight: 500;
            transition: all 0.15s ease;
            text-decoration: none;
        }
        .export-btn:hover {
            background: var(--surface-hover);
            color: var(--text-primary);
        }

        /* ── Error Message ── */
        .msg-bubble.error{background:var(--danger-light);border-color:#fecaca;color:var(--danger)}
        .msg-bubble.error .msg-label{color:var(--danger)}

        /* ── Empty State ── */
        .empty-state{
            display:flex;flex-direction:column;align-items:center;justify-content:center;
            height:100%;gap:16px;color:var(--text-muted);padding:40px 20px;
        }
        .empty-icon{font-size:48px;opacity:0.4}
        .empty-title{font-size:16px;font-weight:600;color:var(--text-secondary)}
        .empty-desc{font-size:13px;text-align:center;max-width:360px;line-height:1.6}
        .empty-hints{display:flex;flex-wrap:wrap;gap:6px;justify-content:center;margin-top:4px}
        .empty-hint{
            font-size:12px;padding:4px 10px;border-radius:var(--radius-full);
            background:var(--gray-light);color:var(--text-secondary);
            cursor:pointer;transition:all 0.15s;border:none;
        }
        .empty-hint:hover{background:var(--accent-light);color:var(--accent)}

        /* ── Input Bar ── */
        .input-bar{
            flex-shrink:0;padding:12px 16px;
            background:var(--surface);border-top:1px solid var(--border);
        }
        .input-inner{
            max-width:860px;margin:0 auto;
            display:flex;gap:10px;align-items:center;
        }
        .input-inner input{
            flex:1;padding:12px 18px;border-radius:var(--radius-full);
            border:1.5px solid var(--border);outline:none;
            font-size:14px;font-family:inherit;
            background:var(--bg);color:var(--text-primary);
            transition:border-color 0.2s,box-shadow 0.2s;
        }
        .input-inner input:focus{
            border-color:var(--accent);
            box-shadow:0 0 0 3px rgba(37,99,235,0.1);
        }
        .input-inner input::placeholder{color:var(--text-muted)}
        .input-inner button{
            padding:12px 22px;border-radius:var(--radius-full);
            border:none;background:var(--accent);color:white;
            font-weight:600;font-size:14px;cursor:pointer;
            transition:all 0.2s;white-space:nowrap;
            display:flex;align-items:center;gap:6px;
        }
        .input-inner button:hover{background:var(--accent-hover);transform:translateY(-1px)}
        .input-inner button:active{transform:translateY(0)}
        .input-inner button:disabled{opacity:0.5;cursor:not-allowed;transform:none}
        .input-inner button .spinner{
            width:16px;height:16px;border-radius:50%;
            border:2px solid rgba(255,255,255,0.3);
            border-top-color:white;animation:spin 0.7s linear infinite;
            display:none;
        }
        .input-inner button.loading .spinner{display:inline-block}
        .input-inner button.loading .btn-text{display:none}
        @keyframes spin{to{transform:rotate(360deg)}}

        /* ── Animations ── */
        @keyframes fadeIn{
            from{opacity:0;transform:translateY(8px)}
            to{opacity:1;transform:translateY(0)}
        }
        @keyframes slideUp{
            from{opacity:0;transform:translateY(12px)}
            to{opacity:1;transform:translateY(0)}
        }

        /* ── Responsive ── */
        @media (max-width:640px){
            .header{padding:0 16px}
            .header-badge{display:none}
            .chat-container{padding:12px 10px}
            .msg-bubble{max-width:92%;font-size:13px;padding:10px 14px}
            .input-bar{padding:10px 12px}
            .input-inner input{padding:10px 14px;font-size:13px}
            .input-inner button{padding:10px 16px;font-size:13px}
            table.bot-table{font-size:12px;min-width:auto}
            table.bot-table th,table.bot-table td{padding:6px 8px}
        }
    </style>
</head>
<body>

    <!-- ═══ Header ═══ -->
    <header class="header">
        <div class="header-left">
            <div>
                <h1>Multi-Source Chatbot</h1>
                <p>Query your databases with natural language</p>
            </div>
        </div>
    </header>

    <!-- ═══ Messages ═══ -->
    <div class="chat-container" id="chat">
        <!-- Empty state (shown by CSS when no children) -->
    </div>

    <!-- ═══ Input Bar ═══ -->
    <div class="input-bar">
        <div class="input-inner">
            <input id="message" type="text"
                   placeholder="Ask anything — e.g. show customers, top 5 products, count sales"
                   autocomplete="off" enterkeyhint="send">
            <button id="send">
                <span class="btn-text">Send</span>
                <span class="spinner"></span>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>
            </button>
        </div>
    </div>

    <script>
        // ─── Helpers ─────────────────────────────────────────────────

        /** Format timestamp as HH:MM */
        function now(){const d=new Date();return String(d.getHours()).padStart(2,'0')+':'+String(d.getMinutes()).padStart(2,'0')}

        /** Escape HTML entities */
        function esc(s){const d=document.createElement('div');d.appendChild(document.createTextNode(s));return d.innerHTML}

        /** Safely parse Markdown, Bold, Lists, and Paragraphs */
        function renderMarkdownText(raw){
            if(!raw) return '';
            let text = esc(String(raw));
            text = text.replace(/^###\s+(.*?)$/gm, '<div style="font-weight:700;font-size:14.5px;margin:8px 0 4px;color:var(--text-primary);">$1</div>');
            text = text.replace(/^##\s+(.*?)$/gm, '<div style="font-weight:700;font-size:14.5px;margin:8px 0 4px;color:var(--text-primary);">$1</div>');
            text = text.replace(/^#\s+(.*?)$/gm, '<div style="font-weight:700;font-size:15px;margin:8px 0 4px;color:var(--text-primary);">$1</div>');
            text = text.replace(/^---+$|^\*\*\*+$/gm, '<hr style="border:0;height:1px;background:var(--border);margin:10px 0;">');
            text = text.replace(/\*\*(.*?)\*\*/g, '<strong style="font-weight:650;color:var(--text-primary);">$1</strong>');
            text = text.replace(/__(.*?)__/g, '<strong style="font-weight:650;color:var(--text-primary);">$1</strong>');
            text = text.replace(/`([^`]+)`/g, '<code style="background:var(--surface-hover);padding:2px 5px;border-radius:4px;font-size:12px;border:1px solid var(--border);">$1</code>');
            text = text.replace(/^(\d+)[\.\)]\s+(.*?)$/gm, '<div style="display:flex;align-items:flex-start;margin-bottom:4px;"><span style="color:var(--accent);font-weight:650;margin-right:6px;min-width:18px;">$1.</span><span>$2</span></div>');
            text = text.replace(/^[\-\*•]\s+(.*?)$/gm, '<div style="display:flex;align-items:flex-start;margin-bottom:4px;"><span style="color:var(--accent);font-weight:bold;margin-right:8px;">•</span><span>$1</span></div>');

            const paragraphs = text.split(/\n\n+/);
            const formatted = paragraphs.map(p => {
                const tr = p.trim();
                if(!tr) return '';
                if(tr.startsWith('<div') || tr.startsWith('<hr')) return tr.replace(/\n/g, '<br>');
                return `<div style="margin-bottom:8px;">${tr.replace(/\n/g, '<br>')}</div>`;
            });
            return `<div style="line-height:1.65;">${formatted.join('')}</div>`;
        }

        /** Build a message bubble element */
        function bubble(content, role, extra=''){
            const row=document.createElement('div');
            row.className='msg-row '+role;
            row.style.animation='slideUp 0.25s ease-out';
            row.innerHTML='<div class="msg-bubble'+(extra?' '+extra:'')+'">'
                +'<div class="msg-label">'+(role==='user'?'You':'Assistant')+'</div>'
                +content
                +'<span class="msg-time">'+now()+'</span>'
                +'</div>';
            return row;
        }

        /** Render table data into HTML */
        function renderTable(rows){
            if(!rows||!rows.length) return '<div style="padding:12px;color:var(--text-muted);font-size:13px">No rows returned</div>';
            const cols=Object.keys(rows[0]);
            let h='<div class="table-wrap"><table class="bot-table"><thead><tr>';
            cols.forEach(c=>h+='<th>'+esc(c)+'</th>');
            h+='</tr></thead><tbody>';
            rows.forEach(r=>{
                h+='<tr>';
                cols.forEach(c=>h+='<td>'+(r[c]??'<span style="color:var(--text-muted)">—</span>')+'</td>');
                h+='</tr>';
            });
            h+='</tbody></table></div>';
            h+='<div class="row-count">📋 '+rows.length+' row'+(rows.length!==1?'s':'')+' returned</div>';
            return h;
        }

        /** Build confidence badge HTML */
        function confidenceHTML(pct){
            const level=pct>=70?'high':(pct>=40?'medium':'low');
            const labels={high:'High confidence',medium:'Medium confidence',low:'Low confidence'};
            return '<div class="confidence-badge '+level+'">'
                +'<span class="confidence-dot"></span>'
                +labels[level]+' &middot; '+pct.toFixed(1)+'%</div>';
        }

        /** Add a user (query) message */
        function addUserMsg(text){
            const el=bubble(esc(text),'user');
            document.getElementById('chat').appendChild(el);
            scrollBottom();
        }

        /** Add a bot (response) message */
        function addBotMsg(html){
            const el=bubble(html,'bot');
            document.getElementById('chat').appendChild(el);
            scrollBottom();
        }

        /** Add an error message */
        function addErrorMsg(text){
            const el=bubble('<div style="display:flex;align-items:center;gap:8px">⚠️ '+esc(text)+'</div>','bot','error');
            document.getElementById('chat').appendChild(el);
            scrollBottom();
        }

        /** Remove typing indicator if present */
        function removeTyping(){
            const t=document.querySelector('.typing-indicator');
            if(t){t.closest('.msg-row')?.remove()}
        }

        /** Show typing indicator */
        function showTyping(){
            removeTyping();
            const row=document.createElement('div');
            row.className='msg-row bot';row.style.animation='slideUp 0.2s ease-out';
            row.innerHTML='<div class="msg-bubble" style="padding:14px 18px">'
                +'<div class="typing-indicator"><span></span><span></span><span></span></div>'
                +'</div>';
            document.getElementById('chat').appendChild(row);
            scrollBottom();
        }

        /** Scroll chat to bottom */
        function scrollBottom(){
            const c=document.getElementById('chat');
            requestAnimationFrame(()=>c.scrollTop=c.scrollHeight);
        }

        // ─── Send Query ──────────────────────────────────────────────

        function sendQuery(){
            const $btn=$('#send');
            const $msg=$('#message');
            const query=$msg.val().trim();
            if(!query) return;

            // Clear empty-state if present
            const chat=document.getElementById('chat');

            // Show user message
            addUserMsg(query);
            $msg.val('').focus();

            // Show typing, disable button
            showTyping();
            $btn.prop('disabled',true).addClass('loading');

            $.ajax({
                url: '/chat/send',
                type: 'POST',
                dataType: 'json',
                data: { message: query, _token: '{{ csrf_token() }}' },
                success: function(response){
                    removeTyping();
                    if(response && (response.type==='table'||response.type==='text')){
                        let html='';
                        // Confidence
                        if(response.confidence!==undefined){
                            const pct=response.confidence*100;
                            html+=confidenceHTML(pct);
                        }
                        // Content
                        let rawMsg = response.message ?? '';
                        if(response.type==='table'){
                            html+=renderTable(response.data||[]);
                        } else {
                            html+=renderMarkdownText(rawMsg || 'No data');
                        }

                        // Chart & Export Parsing (Only for queries that warrant visual charts)
                        const parsed = parseAnalyticsData(rawMsg);
                        let chartId = null;
                        let initialChartType = 'bar';

                        if (parsed && isChartNeeded(query, parsed)) {
                            initialChartType = detectOptimalChartType(query, parsed);
                            chartId = 'chart_pub_' + Math.random().toString(36).substring(2, 9);
                            window.activeChatCharts = window.activeChatCharts || {};
                            window.activeChatCharts[chartId] = parsed;

                            html += `
                                <div class="analytics-chart-card" id="card_${chartId}">
                                    <div class="analytics-chart-header">
                                        <div class="analytics-chart-title">
                                            <i class="ri-pie-chart-2-fill text-primary"></i>
                                            <span>Visual Breakdown (${parsed.title})</span>
                                        </div>
                                        <div class="chart-toggle-group">
                                            <button type="button" class="chart-toggle-btn ${initialChartType === 'bar' ? 'active' : ''}" onclick="switchChatChartType('${chartId}', 'bar', this)">Bar</button>
                                            <button type="button" class="chart-toggle-btn ${initialChartType === 'doughnut' ? 'active' : ''}" onclick="switchChatChartType('${chartId}', 'doughnut', this)">Donut</button>
                                            <button type="button" class="chart-toggle-btn ${initialChartType === 'line' ? 'active' : ''}" onclick="switchChatChartType('${chartId}', 'line', this)">Line</button>
                                        </div>
                                    </div>
                                    <div class="chart-canvas-wrapper">
                                        <canvas id="${chartId}"></canvas>
                                    </div>
                                    <div class="export-actions-bar">
                                        <button type="button" class="export-btn" onclick="triggerChatExport('${chartId}', 'pdf')">
                                            <i class="ri-file-pdf-line text-danger"></i> PDF
                                        </button>
                                        <button type="button" class="export-btn" onclick="triggerChatExport('${chartId}', 'xlsx')">
                                            <i class="ri-file-excel-line text-success"></i> Excel
                                        </button>
                                        <button type="button" class="export-btn" onclick="triggerChatExport('${chartId}', 'csv')">
                                            <i class="ri-file-text-line text-primary"></i> CSV
                                        </button>
                                        <button type="button" class="export-btn" onclick="copyChatReport(this, '${escapeJs(rawMsg)}')">
                                            <i class="ri-file-copy-line"></i> Copy
                                        </button>
                                    </div>
                                </div>
                            `;
                        }

                        addBotMsg(html);

                        if (chartId && parsed) {
                            setTimeout(() => {
                                initChatChartJs(chartId, parsed, initialChartType);
                            }, 50);
                        }
                    } else {
                        addBotMsg('<div style="color:var(--text-muted)">No results returned.</div>');
                    }
                },
                error: function(xhr){
                    removeTyping();
                    let msg='Request failed';
                    try{
                        const r=xhr.responseJSON;
                        if(r&&r.message) msg=r.message;
                        else if(r&&r.errors) msg=Object.values(r.errors).flat().join(', ');
                    }catch(e){}
                    addErrorMsg(msg);
                },
                complete: function(){
                    $btn.prop('disabled',false).removeClass('loading');
                }
            });
        }

        // ─── Quick Hints (empty-state suggestions) ───────────────────

        function insertHint(text){
            $('#message').val(text).focus();
        }

        function buildEmptyState(){
            const hints=['show customers','top 5 products','count sales','show employees','list suppliers','sales over 10000'];
            const html='<div class="empty-state">'
                +'<div class="empty-icon">💬</div>'
                +'<div class="empty-title">Start a Conversation</div>'
                +'<div class="empty-desc">Ask questions in natural language. Your query is routed to the right database automatically using semantic AI.</div>'
                +'<div class="empty-hints">'
                +hints.map(h=>'<button class="empty-hint" onclick="insertHint(\''+h+'\')">'+h+'</button>').join('')
                +'</div>'
                +'</div>';
            return html;
        }

        // ── Visual Data Charts & Export Handlers ──
        window.chatChartJsInstances = window.chatChartJsInstances || {};

        function isChartNeeded(query, parsed) {
            if (!parsed || !parsed.dataPoints || parsed.dataPoints.length === 0) return false;
            
            const q = (query || '').toLowerCase();
            const explicitKeywords = [
                'chart', 'graph', 'চার্ট', 'গ্রাফ', 'pie', 'bar', 'line', 'donut', 
                'পাই', 'ডোনাট', 'বার', 'লাইন', 'visual', 'ভিজ্যুয়াল', 'breakdown', 'ব্রেকডাউন'
            ];
            const isExplicit = explicitKeywords.some(kw => q.includes(kw));

            // Show chart if user explicitly asked for a chart/graph OR if dataset has 2 or more data points (comparisons / breakdown)
            return isExplicit || parsed.dataPoints.length >= 2;
        }

        function detectOptimalChartType(query, parsed) {
            const q = (query || '').toLowerCase();

            // 1. Explicit user chart type request has top priority
            if (q.includes('pie') || q.includes('donut') || q.includes('পাই') || q.includes('ডোনাট')) return 'doughnut';
            if (q.includes('bar') || q.includes('বার') || q.includes('কলাম') || q.includes('column')) return 'bar';
            if (q.includes('line') || q.includes('লাইন') || q.includes('গ্রাফ') || q.includes('trend') || q.includes('ট্রেন্ড')) return 'line';

            // 2. Semantic detection based on question & dataset title
            const title = (parsed?.title || '').toLowerCase();
            const combined = q + ' ' + title;

            // Distribution / Share / Methods / Categories -> Donut
            if (combined.includes('method') || combined.includes('মেথড') || combined.includes('share') || combined.includes('ভাগ') || 
                combined.includes('অনুপাত') || combined.includes('ratio') || combined.includes('category') || combined.includes('ক্যাটাগরি') ||
                combined.includes('payment') || combined.includes('পেমেন্ট') || combined.includes('status') || combined.includes('স্ট্যাটাস')) {
                return 'doughnut';
            }

            // Time-series / Trends / Daily / Monthly -> Line
            if (combined.includes('trend') || combined.includes('ট্রেন্ড') || combined.includes('daily') || combined.includes('দৈনিক') || 
                combined.includes('monthly') || combined.includes('মাসিক') || combined.includes('দিন') || combined.includes('days') || 
                combined.includes('date') || combined.includes('তারিখ') || combined.includes('history') || combined.includes('timeline')) {
                return 'line';
            }

            // Default for comparisons, rankings, entity lists -> Bar
            return 'bar';
        }

        function parseAnalyticsData(text) {
            if (!text || typeof text !== 'string') return null;
            const lines = text.split('\n');
            const dataPoints = [];
            let mainTitle = 'Metrics Breakdown';

            const metricRegex = /[-*•]\s*\**([A-Za-z0-9\s_&-]+?)\**:\s*([^\n\r]+)/;
            
            for (const line of lines) {
                const trimmed = line.trim();
                if (trimmed.startsWith('#') || trimmed.includes('**Business Analytics') || trimmed.includes('Summary')) {
                    const cleanTitle = trimmed.replace(/^[#* \-_]+|[#* \-_]+$/g, '');
                    if (cleanTitle) mainTitle = cleanTitle;
                }

                const match = trimmed.match(metricRegex);
                if (match) {
                    const label = match[1].trim();
                    const rawVal = match[2].trim();
                    const cleanNumStr = rawVal.replace(/[^0-9.-]/g, '');
                    const num = parseFloat(cleanNumStr);

                    if (!isNaN(num) && num > 0) {
                        dataPoints.push({
                            label: label,
                            raw: rawVal,
                            value: num,
                        });
                    }
                }
            }

            if (dataPoints.length === 0) return null;

            return {
                title: mainTitle,
                dataPoints: dataPoints,
            };
        }

        function initChatChartJs(canvasId, parsedData, type = 'bar') {
            const ctx = document.getElementById(canvasId);
            if (!ctx) return;

            if (window.chatChartJsInstances[canvasId]) {
                window.chatChartJsInstances[canvasId].destroy();
            }

            const labels = parsedData.dataPoints.map(dp => dp.label);
            const dataValues = parsedData.dataPoints.map(dp => dp.value);

            const palette = [
                'rgba(37, 99, 235, 0.85)',
                'rgba(16, 185, 129, 0.85)',
                'rgba(245, 158, 11, 0.85)',
                'rgba(139, 92, 246, 0.85)',
                'rgba(236, 72, 153, 0.85)'
            ];

            const borderPalette = [
                '#1d4ed8',
                '#059669',
                '#d97706',
                '#7c3aed',
                '#db2777'
            ];

            const chartConfig = {
                type: type,
                data: {
                    labels: labels,
                    datasets: [{
                        label: parsedData.title,
                        data: dataValues,
                        backgroundColor: type === 'line' ? 'rgba(37, 99, 235, 0.15)' : palette.slice(0, dataValues.length),
                        borderColor: type === 'line' ? '#2563eb' : borderPalette.slice(0, dataValues.length),
                        borderWidth: 1.5,
                        fill: type === 'line',
                        tension: 0.35,
                        borderRadius: type === 'bar' ? 6 : 0,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: type === 'doughnut' || type === 'pie',
                            position: 'bottom',
                            labels: { boxWidth: 10, font: { size: 10 } }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const orig = parsedData.dataPoints[context.dataIndex]?.raw || context.raw;
                                    return ` ${context.label}: ${orig}`;
                                }
                            }
                        }
                    },
                    scales: type === 'doughnut' || type === 'pie' ? {} : {
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgba(0,0,0,0.04)' },
                            ticks: { font: { size: 10 } }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { font: { size: 10 } }
                        }
                    }
                }
            };

            window.chatChartJsInstances[canvasId] = new Chart(ctx, chartConfig);
        }

        function switchChatChartType(chartId, newType, btn) {
            const card = document.getElementById(`card_${chartId}`);
            if (card) {
                card.querySelectorAll('.chart-toggle-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
            }

            const parsed = window.activeChatCharts?.[chartId];
            if (parsed) {
                initChatChartJs(chartId, parsed, newType);
            }
        }

        async function triggerChatExport(chartId, format) {
            const parsed = window.activeChatCharts?.[chartId];
            if (!parsed) {
                alert('No structured chart data found to export.');
                return;
            }

            const headers = ['Metric / Label', 'Value (Formatted)'];
            const rows = parsed.dataPoints.map(dp => [dp.label, dp.raw]);
            const summary = {
                'Report Title': parsed.title,
                'Generated By': 'AI Analytics Engine',
                'Export Date': new Date().toLocaleString(),
            };

            const payload = {
                format: format,
                title: parsed.title,
                headers: headers,
                rows: rows,
                summary: summary,
            };

            try {
                const response = await fetch("{{ route('export.analytics') }}", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": "{{ csrf_token() }}"
                    },
                    body: JSON.stringify(payload)
                });

                if (!response.ok) {
                    throw new Error("Export failed with status " + response.status);
                }

                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                const ext = format === 'xlsx' ? 'xlsx' : (format === 'pdf' ? 'pdf' : 'csv');
                a.download = `analytics-report-${Date.now()}.${ext}`;
                document.body.appendChild(a);
                a.click();
                a.remove();
                window.URL.revokeObjectURL(url);
            } catch (err) {
                console.error("Export error:", err);
                alert("Could not complete export: " + err.message);
            }
        }

        function copyChatReport(btn, text) {
            if (!navigator.clipboard) {
                const ta = document.createElement('textarea');
                ta.value = text;
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
            } else {
                navigator.clipboard.writeText(text);
            }

            const origHtml = btn.innerHTML;
            btn.innerHTML = '<i class="ri-check-line text-success"></i> Copied!';
            setTimeout(() => {
                btn.innerHTML = origHtml;
            }, 2000);
        }

        function escapeJs(str) {
            return String(str).replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '\\"');
        }
    </script>

</body>
</html>
