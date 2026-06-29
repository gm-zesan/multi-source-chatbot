<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Multi-Source Chatbot</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
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
                        if(response.type==='table'){
                            html+=renderTable(response.data||[]);
                        } else {
                            html+='<div>'+esc(response.message??'No data')+'</div>';
                        }
                        addBotMsg(html);
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

        // ─── Init ────────────────────────────────────────────────────

        $(function(){
            // Empty state
            $('#chat').html(buildEmptyState());

            // Send handlers
            $('#send').on('click',sendQuery);
            $('#message').on('keydown',function(e){if(e.key==='Enter')sendQuery()});
        });
    </script>

</body>
</html>
