<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; color: #1a1a1a; margin: 0; padding: 0; background: #f5f5f5; }
        .wrapper { max-width: 580px; margin: 32px auto; background: #fff; border-radius: 8px; overflow: hidden; border: 1px solid #e0e0e0; }
        .header { background: #dc2626; padding: 24px 32px; }
        .header h1 { color: #fff; margin: 0; font-size: 20px; }
        .body { padding: 28px 32px; }
        .alert-box { background: #fef2f2; border: 1px solid #fca5a5; border-radius: 6px; padding: 16px 20px; margin-bottom: 24px; }
        .alert-box p { margin: 0; font-size: 14px; color: #991b1b; }
        .detail-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .detail-table td { padding: 8px 0; border-bottom: 1px solid #f0f0f0; }
        .detail-table td:first-child { color: #666; width: 40%; }
        .detail-table td:last-child { font-weight: 600; }
        .mitigation { margin-top: 24px; }
        .mitigation h3 { font-size: 14px; text-transform: uppercase; letter-spacing: 0.05em; color: #555; margin-bottom: 12px; }
        .mitigation ul { margin: 0; padding-left: 20px; font-size: 14px; line-height: 1.8; }
        .cta { margin-top: 28px; text-align: center; }
        .cta a { display: inline-block; background: #dc2626; color: #fff; text-decoration: none; padding: 12px 28px; border-radius: 6px; font-size: 14px; font-weight: 600; }
        .footer { background: #f9f9f9; padding: 16px 32px; font-size: 12px; color: #999; border-top: 1px solid #e0e0e0; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <h1>🔴 SLA Breach — Immediate Action Required</h1>
    </div>
    <div class="body">
        <div class="alert-box">
            <p>
                The document below has <strong>exceeded its allowed SLA window</strong> in
                <strong>{{ $department->name }}</strong> and is now overdue. This breach has been logged.
                Immediate processing is required.
            </p>
        </div>

        <table class="detail-table">
            <tr>
                <td>Tracking Number</td>
                <td>{{ $document->tracking_number }}</td>
            </tr>
            <tr>
                <td>Document Type</td>
                <td>{{ $document->document_type }}</td>
            </tr>
            <tr>
                <td>Citizen Name</td>
                <td>{{ $document->citizen_name ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td>Current Department</td>
                <td>{{ $department->name }}</td>
            </tr>
            <tr>
                <td>SLA Limit</td>
                <td>{{ $department->sla_hours }} hours</td>
            </tr>
            <tr>
                <td>Document Status</td>
                <td style="color:#dc2626;">{{ ucfirst(str_replace('_', ' ', $document->status)) }}</td>
            </tr>
        </table>

        <div class="mitigation">
            <h3>Mitigation Steps</h3>
            <ul>
                <li><strong>Immediately</strong> assign the document to an available staff member for processing.</li>
                <li>Escalate to the department head if no staff member is available right now.</li>
                <li>Scan the document OUT as soon as it is processed to update the citizen's timeline.</li>
                <li>Document the reason for the delay in the system remarks field for audit purposes.</li>
                <li>If a systemic bottleneck is causing delays, notify the administrator to adjust routing or SLA windows.</li>
            </ul>
        </div>

        <div class="cta">
            <a href="{{ url('/track/' . $document->tracking_number) }}">View Full Document Timeline</a>
        </div>
    </div>
    <div class="footer">
        This is an automated SLA breach notification from SPeED TraQR. Do not reply to this email.<br>
        Tracking URL: {{ url('/track/' . $document->tracking_number) }}
    </div>
</div>
</body>
</html>
