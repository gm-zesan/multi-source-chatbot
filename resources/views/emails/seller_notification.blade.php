<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $emailSubject }}</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333333; margin: 0; padding: 20px;">
    <div style="max-width: 600px; margin: 0 auto; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 24px;">
        <h2 style="margin-top: 0; color: #1a202c; font-size: 20px;">{{ $emailSubject }}</h2>
        @if(!empty($sellerName))
            <p style="font-weight: 600; color: #4a5568;">Dear {{ $sellerName }},</p>
        @endif
        <div style="margin-top: 16px; font-size: 15px; color: #2d3748; white-space: pre-wrap;">{{ $emailMessage }}</div>
        <hr style="margin-top: 24px; border: none; border-top: 1px solid #edf2f7;">
        <p style="font-size: 12px; color: #a0aec0; margin-bottom: 0;">This is an automated notification from your store assistant.</p>
    </div>
</body>
</html>
