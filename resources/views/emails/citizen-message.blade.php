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
        .msg { background: #eef5f0; border: 1px solid #cfe3d6; border-radius: 6px; padding: 16px 20px; margin: 12px 0; white-space: pre-wrap; }
        .meta { font-size: 13px; color: #5b6b62; }
        .cta { margin-top: 24px; text-align: center; }
        .cta a { display: inline-block; background: #167a3a; color: #fff; text-decoration: none; padding: 12px 28px; border-radius: 6px; font-size: 14px; font-weight: 600; }
        .footer { background: #f9f9f9; padding: 16px 32px; font-size: 12px; color: #999; border-top: 1px solid #e0e0e0; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header"><h1>A citizen is waiting on an answer</h1></div>
    <div class="body">
        <p>Hello {{ $recipient->name }},</p>
        <p>
            {{ $document->citizen_name ?? 'The requester' }} posted a message on
            <strong>{{ $document->tracking_number }}</strong>
            @if($document->document_type)<span class="meta">({{ $document->document_type }})</span>@endif:
        </p>
        <div class="msg">{{ $body }}</div>
        <p class="meta">Reply from the request's Collaboration panel — your reply is sent straight back to them.</p>
        <div class="cta"><a href="{{ $reviewUrl }}">Open the request</a></div>
    </div>
    <div class="footer">SPeED TraQR · This is an automated message — please do not reply.</div>
</div>
</body>
</html>
