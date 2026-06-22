<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Chatbot</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <style>
        :root{--bg:#f7f9fb;--card:#ffffff;--muted:#6b7280;--accent:#2563eb;--border:#e6eef6}
        *{box-sizing:border-box;font-family:Inter,system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial}
        body{margin:0;background:var(--bg);color:#0f1724;min-height:100vh;display:flex;flex-direction:column}
        .container{width:100%;max-width:980px;margin:40px auto;padding:0 24px}
        .card{background:var(--card);border-radius:12px;padding:20px;box-shadow:0 6px 18px rgba(15,23,36,0.06);border:1px solid var(--border)}
        .header{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}
        .title{display:flex;gap:12px;align-items:center}
        .logo{width:48px;height:48px;border-radius:10px;display:inline-flex;align-items:center;justify-content:center;background:var(--accent);font-weight:700;color:white;font-size:16px}
        h1{margin:0;font-size:18px;font-weight:700}
        p.lead{margin:0;color:var(--muted);font-size:13px}

        .chat-area{display:flex;gap:20px}
        .results{flex:1;min-height:360px;background:transparent;padding:8px;border-radius:8px;border:1px dashed transparent;overflow:auto}

        table.data-table{width:100%;border-collapse:collapse;color:inherit;background:transparent}
        table.data-table th,table.data-table td{padding:10px 12px;border-bottom:1px solid #f1f5f9;text-align:left}
        table.data-table thead th{font-weight:600;font-size:13px;color:var(--muted)}

        .empty{display:flex;align-items:center;justify-content:center;height:100%;color:var(--muted)}
        .msg{padding:12px;border-radius:8px;background:#f1f5f9;color:var(--muted)}

        /* Bottom full-width input bar */
        .bottom-bar{position:fixed;left:0;right:0;bottom:18px;display:flex;justify-content:center;padding:0 16px;pointer-events:none}
        .bottom-container{pointer-events:auto;width:100%;max-width:980px}
        .bottom-card{display:flex;gap:8px;align-items:center;background:var(--card);padding:10px;border-radius:999px;box-shadow:0 8px 24px rgba(15,23,36,0.06);border:1px solid var(--border)}
        .bottom-card input{flex:1;padding:12px 16px;border-radius:999px;border:1px solid rgba(15,23,36,0.06);outline:none;background:transparent;font-size:15px}
        .bottom-card button{background:var(--accent);color:white;padding:10px 16px;border-radius:999px;border:0;font-weight:700;cursor:pointer}
        .bottom-card button:disabled{opacity:0.6;cursor:wait}

        @media (max-width:600px){.container{margin:20px auto;padding:0 12px}.bottom-bar{bottom:12px;padding:0 10px}}
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <div class="title">
                    <div class="logo">DB</div>
                    <div>
                        <h1>Laravel Database Chatbot</h1>
                        <p class="lead">Ask natural questions — results appear as a table or message.</p>
                    </div>
                </div>
                <div class="meta">
                    <p class="lead">Production · Secure</p>
                </div>
            </div>

            <div class="chat-area">
                <div class="results" id="chat">
                    <div class="empty">No results yet. Use the input at the bottom to query.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="bottom-bar">
        <div class="bottom-container">
            <div class="bottom-card">
                <input id="message" placeholder="Ask anything — e.g. show customers" autocomplete="off">
                <button id="send">Send</button>
            </div>
        </div>
    </div>

    <script>
        function renderTable(rows){
            if(!rows || !rows.length) return '<div class="empty">No rows returned</div>';
            let html = '<table class="data-table">';
            html += '<thead><tr>';
            const cols = Object.keys(rows[0]);
            cols.forEach(c => html += `<th>${c}</th>`);
            html += '</tr></thead><tbody>';
            rows.forEach(r => {
                html += '<tr>';
                cols.forEach(c => html += `<td>${r[c] ?? ''}</td>`);
                html += '</tr>';
            });
            html += '</tbody></table>';
            return html;
        }

        $(function(){
            $('#send').on('click', function(){
                const $btn = $(this);
                const $msg = $('#message');
                const query = $msg.val().trim();
                if(!query) return;
                $btn.prop('disabled', true).text('Sending...');
                $('#chat').html('<div class="msg">Searching…</div>');

                $.ajax({
                    url: '/chat/send',
                    type: 'POST',
                    dataType: 'json',
                    data: { message: query, _token: '{{ csrf_token() }}' },
                    success: function(response){
                        if(response && (response.type === 'table' || response.type === 'text')){
                            if(response.type === 'table'){
                                $('#chat').html(renderTable(response.data || []));
                            } else {
                                $('#chat').html(`<div class="msg">${response.message ?? 'No data'}</div>`);
                            }
                        } else {
                            $('#chat').html('<div class="empty">No results</div>');
                        }
                    },
                    error: function(xhr, status, err){
                        $('#chat').html(`<div class="msg" style="color:#b91c1c">Error: ${err || status}</div>`);
                    },
                    complete: function(){
                        $btn.prop('disabled', false).text('Send');
                    }
                });
            });
            $('#message').on('keydown', function(e){ if(e.key === 'Enter') $('#send').trigger('click'); });
        });
    </script>
</body>
</html>