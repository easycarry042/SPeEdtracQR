<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; color: #1a1a1a; margin: 0; padding: 0; background: #f5f5f5; }
        .wrapper { max-width: 580px; margin: 32px auto; background: #fff; border-radius: 8px; overflow: hidden; border: 1px solid #e0e0e0; }
        .header { background: #b45309; padding: 24px 32px; }
        .header h1 { color: #fff; margin: 0; font-size: 20px; }
        .body { padding: 28px 32px; font-size: 14px; line-height: 1.6; }
        .reason { background: #fffbeb; border: 1px solid #fcd34d; border-radius: 6px; padding: 14px 18px; margin: 12px 0; color: #92400e; }
        .due { font-size: 13px; color: #92400e; margin-top: 4px; }
        .cta { margin-top: 24px; text-align: center; }
        .cta a { display: inline-block; background: #b45309; color: #fff; text-decoration: none; padding: 12px 28px; border-radius: 6px; font-size: 14px; font-weight: 600; }
        .footer { background: #f9f9f9; padding: 16px 32px; font-size: 12px; color: #999; border-top: 1px solid #e0e0e0; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header"><h1>⏸ Action needed on your request</h1></div>
    <div class="body">
        <p>Hello {{ $document->citizen_name ?? 'there' }},</p>
        <p>Your request <strong>{{ $document->tracking_number }}</strong> is on hold while we wait for something from you:</p>
        <div class="reason">
            {{ $reason }}
            @if($holdUntil)
                <div class="due">Please respond by {{ $holdUntil->format('M d, Y') }}.</div>
            @endif
        </div>
        <p>Open your tracking page to reply or upload the requested documents. Once we have what we need, our staff will resume processing.</p>
        <div class="cta"><a href="{{ $trackingUrl }}">Respond now</a></div>
    </div>
    <div class="footer">SPeED TraQR · This is an automated message — please do not reply.</div>
</div>
</body>
</html>
