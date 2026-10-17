<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; color: #1a1a1a; margin: 0; padding: 0; background: #f5f5f5; }
        .wrapper { max-width: 580px; margin: 32px auto; background: #fff; border-radius: 8px; overflow: hidden; border: 1px solid #e0e0e0; }
        .header { padding: 24px 32px; }
        .header h1 { color: #fff; margin: 0; font-size: 20px; }
        .body { padding: 28px 32px; font-size: 14px; line-height: 1.6; }
        .doc { font-weight: 600; }
        .reason { border-radius: 6px; padding: 14px 18px; margin: 12px 0; }
        .cta { margin-top: 24px; text-align: center; }
        .cta a { display: inline-block; color: #fff; text-decoration: none; padding: 12px 28px; border-radius: 6px; font-size: 14px; font-weight: 600; }
        .footer { background: #f9f9f9; padding: 16px 32px; font-size: 12px; color: #999; border-top: 1px solid #e0e0e0; }
    </style>
</head>
@php $rejected = $requirement->isRejected(); @endphp
<body>
<div class="wrapper">
    <div class="header" style="background: {{ $rejected ? '#b91c1c' : '#b45309' }};">
        <h1>{{ $rejected ? '✕ A document was rejected' : '↻ A document needs revision' }}</h1>
    </div>
    <div class="body">
        <p>Hello {{ $document->citizen_name ?? 'there' }},</p>
        <p>For your request <strong>{{ $document->tracking_number }}</strong>, our staff reviewed the following document:</p>
        <p class="doc">{{ $requirement->label }} — {{ $requirement->reviewStatusLabel() }}</p>
        @if($requirement->review_comment)
            <div class="reason" style="background: {{ $rejected ? '#fef2f2' : '#fffbeb' }}; border: 1px solid {{ $rejected ? '#fca5a5' : '#fcd34d' }}; color: {{ $rejected ? '#991b1b' : '#92400e' }};">
                {{ $requirement->review_comment }}
            </div>
        @endif
        @if($rejected)
            <p>This document was not accepted. You can view the reason above on your tracking page. Please contact the office if you have questions.</p>
        @else
            <p>Please open your tracking page and re-upload <strong>only this document</strong> with the correction above. You do not need to resubmit anything else.</p>
            <div class="cta"><a style="background: #b45309;" href="{{ $trackingUrl }}">Re-upload the document</a></div>
        @endif
    </div>
    <div class="footer">SPeED TraQR · This is an automated message — please do not reply.</div>
</div>
</body>
</html>
