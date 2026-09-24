<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', 'Helvetica Neue', Arial, sans-serif;
            font-size: 11px;
            color: #1e293b;
            line-height: 1.5;
            margin: 0;
            padding: 20px;
        }
        .header {
            border-bottom: 2px solid #3b82f6;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .header h1 {
            font-size: 18px;
            color: #1e3a8a;
            margin: 0 0 4px 0;
        }
        .meta-info {
            font-size: 10px;
            color: #64748b;
        }
        .message-box {
            margin-bottom: 14px;
            padding: 10px 14px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
        }
        .inbound {
            background-color: #f8fafc;
            border-left: 4px solid #3b82f6;
        }
        .outbound {
            background-color: #ffffff;
            border-left: 4px solid #10b981;
        }
        .msg-header {
            font-size: 9px;
            color: #64748b;
            margin-bottom: 4px;
            display: flex;
            justify-content: space-between;
        }
        .sender-name {
            font-weight: bold;
            color: #1e293b;
        }
        .msg-body {
            font-size: 10.5px;
            white-space: pre-wrap;
            color: #0f172a;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            font-size: 8px;
            border-radius: 4px;
            background-color: #eff6ff;
            color: #1d4ed8;
            font-weight: bold;
        }
        .footer {
            margin-top: 30px;
            font-size: 8px;
            color: #94a3b8;
            text-align: center;
            border-top: 1px solid #e2e8f0;
            padding-top: 8px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $title }}</h1>
        <div class="meta-info">
            Conversation #{{ $conversation->id }} &bull; Customer: {{ $conversation->customer_name ?? 'N/A' }} &bull; Exported: {{ $timestamp }}
        </div>
    </div>

    <div class="messages-list">
        @foreach($messages as $msg)
            @php
                $isInbound = $msg->direction === 'inbound';
                $sender = $isInbound ? ($conversation->customer_name ?? 'Customer') : 'AI Multi-Source Support';
                $route = $msg->response['route'] ?? null;
            @endphp
            <div class="message-box {{ $isInbound ? 'inbound' : 'outbound' }}">
                <div class="msg-header">
                    <span class="sender-name">{{ $sender }} ({{ strtoupper($msg->direction) }})</span>
                    <span>{{ $msg->created_at?->format('Y-m-d H:i:s') }}</span>
                    @if($route)
                        <span class="badge">Route: {{ strtoupper($route) }}</span>
                    @endif
                </div>
                <div class="msg-body">{{ $msg->body }}</div>
            </div>
        @endforeach
    </div>

    <div class="footer">
        Multi-Source Chatbot Platform &bull; Customer Dialogue Archive
    </div>
</body>
</html>
