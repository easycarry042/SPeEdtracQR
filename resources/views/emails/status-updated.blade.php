<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; color: #1a1a1a; margin: 0; padding: 0; background: #f5f5f5; }
        .wrapper { max-width: 580px; margin: 32px auto; background: #fff; border-radius: 8px; overflow: hidden; border: 1px solid #e0e0e0; }
        .header { background: #0f4d28; padding: 24px 32px; }
        .header h1 { color: #fff; margin: 0; font-size: 20px; }
        .body { padding: 28px 32px; font-size: 14px; line-height: 1.6; }
        .stage { display: inline-block; font-size: 15px; font-weight: 700; color: #0f4d28; background: #eef5f0; border: 1px solid #cfe3d6; border-radius: 6px; padding: 8px 16px; margin: 8px 0 16px; }
        .cta { margin-top: 24px; text-align: center; }
        .cta a { display: inline-block; background: #167a3a; color: #fff; text-decoration: none; padding: 12px 28px; border-radius: 6px; font-size: 14px; font-weight: 600; }
        .footer { background: #f9f9f9; padding: 16px 32px; font-size: 12px; color: #999; border-top: 1px solid #e0e0e0; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header"><h1>Status update</h1></div>
    <div class="body">
        <p>Hello {{ $document->citizen_name ?? 'there' }},</p>
        <p>Your request <strong>{{ $document->tracking_number }}</strong> has a new status:</p>
        <div class="stage">{{ $stageLabel }}</div>
        <div class="cta"><a href="{{ $trackingUrl }}">View details</a></div>
    </div>
    <div class="footer">SPeED TraQR · This is an automated message — please do not reply.</div>
</div>
</body>
</html>
