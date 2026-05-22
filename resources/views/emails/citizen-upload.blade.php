<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Citizen document upload</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.5; color: #1a1a1a;">
    <h2 style="color: #1a5c1a;">New files from a citizen</h2>
    <p>A citizen uploaded {{ $fileCount }} file(s) for a ticket currently handled by <strong>{{ $department->name }}</strong>.</p>
    <table cellpadding="6" style="border-collapse: collapse;">
        <tr><td><strong>Tracking #</strong></td><td>{{ $document->tracking_number }}</td></tr>
        <tr><td><strong>Document type</strong></td><td>{{ $document->document_type }}</td></tr>
        <tr><td><strong>Citizen</strong></td><td>{{ $document->citizen_name ?? 'N/A' }}</td></tr>
    </table>
    @if($citizenNote)
        <p><strong>Note from citizen:</strong><br>{{ $citizenNote }}</p>
    @endif
    <p style="margin-top: 1.5rem;">
        <a href="{{ url('/movements?tab=inbox') }}" style="background: #1a5c1a; color: #fff; padding: 10px 16px; text-decoration: none; border-radius: 8px;">Open in Movements</a>
    </p>
    <p style="font-size: 12px; color: #666;">Automated message from SPeED TraQR.</p>
</body>
</html>
