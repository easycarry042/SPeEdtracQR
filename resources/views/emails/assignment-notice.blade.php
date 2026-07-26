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
        .detail-table { width: 100%; border-collapse: collapse; font-size: 14px; margin-top: 8px; }
        .detail-table td { padding: 8px 0; border-bottom: 1px solid #f0f0f0; }
        .detail-table td:first-child { color: #666; width: 40%; }
        .detail-table td:last-child { font-weight: 600; }
        .cta { margin-top: 24px; text-align: center; }
        .cta a { display: inline-block; background: #167a3a; color: #fff; text-decoration: none; padding: 12px 28px; border-radius: 6px; font-size: 14px; font-weight: 600; }
        .footer { background: #f9f9f9; padding: 16px 32px; font-size: 12px; color: #999; border-top: 1px solid #e0e0e0; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header"><h1>New document assigned to you</h1></div>
    <div class="body">
        <p>Hello {{ $assignee->name }},</p>
        <p>You have been assigned a document to advance through its status stages.</p>
        <table class="detail-table">
            <tr><td>Tracking #</td><td>{{ $document->tracking_number }}</td></tr>
            <tr><td>Type</td><td>{{ $document->document_type }}</td></tr>
            <tr><td>Current status</td><td>{{ $document->statusEnum()->label() }}</td></tr>
        </table>
        <div class="cta"><a href="{{ $documentUrl }}">Open document</a></div>
    </div>
    <div class="footer">SPeED TraQR · This is an automated message — please do not reply.</div>
</div>
</body>
</html>
