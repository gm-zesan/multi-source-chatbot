<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $emailSubject }}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; color: #1e293b;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color: #f1f5f9; padding: 40px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width: 580px; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03); border: 1px solid #e2e8f0;">
                    
                    <!-- Header Accent Bar -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%); padding: 24px 32px; text-align: left;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td>
                                        <div style="display: inline-block; background: rgba(255, 255, 255, 0.2); border-radius: 6px; padding: 4px 10px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #ffffff; margin-bottom: 8px;">
                                            Store Notification
                                        </div>
                                        <h1 style="margin: 0; color: #ffffff; font-size: 20px; font-weight: 700; line-height: 1.3;">
                                            {{ $emailSubject }}
                                        </h1>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Body Content -->
                    <tr>
                        <td style="padding: 32px;">
                            @if(!empty($sellerName))
                                <p style="margin: 0 0 16px 0; font-size: 16px; font-weight: 600; color: #334155;">
                                    Hello {{ $sellerName }},
                                </p>
                            @endif

                            <!-- Message Box -->
                            <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin: 16px 0 24px 0;">
                                <p style="margin: 0; font-size: 15px; line-height: 1.6; color: #1e293b; white-space: pre-wrap;">{{ $emailMessage }}</p>
                            </div>

                            <p style="margin: 0; font-size: 14px; line-height: 1.5; color: #64748b;">
                                If you have any questions or need further clarification, please reply directly or contact the administration team.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8fafc; border-top: 1px solid #e2e8f0; padding: 20px 32px; text-align: center;">
                            <p style="margin: 0 0 4px 0; font-size: 13px; font-weight: 600; color: #475569;">
                                {{ config('mail.from.name', 'Store Assistant') }}
                            </p>
                            <p style="margin: 0; font-size: 12px; color: #94a3b8;">
                                Automated communication dispatched via your E-Commerce AI Support System.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
