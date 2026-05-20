<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; color: #1a1a1a; margin: 0; padding: 0; background: #f5f5f5; }
        .wrapper { max-width: 580px; margin: 32px auto; background: #fff; border-radius: 8px; overflow: hidden; border: 1px solid #e0e0e0; }
        .header { background: #d97706; padding: 24px 32px; }
        .header h1 { color: #fff; margin: 0; font-size: 20px; }
        .body { padding: 28px 32px; }
        .alert-box { background: #fffbeb; border: 1px solid #fcd34d; border-radius: 6px; padding: 16px 20px; margin-bottom: 24px; }
        .alert-box p { margin: 0; font-size: 14px; color: #92400e; }
        .detail-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .detail-table td { padding: 8px 0; border-bottom: 1px solid #f0f0f0; }
        .detail-table td:first-child { color: #666; width: 40%; }
        .detail-table td:last-child { font-weight: 600; }
        .mitigation { margin-top: 24px; }
        .mitigation h3 { font-size: 14px; text-transform: uppercase; letter-spacing: 0.05em; color: #555; margin-bottom: 12px; }
        .mitigation ul { margin: 0; padding-left: 20px; font-size: 14px; line-height: 1.8; }
        .cta { margin-top: 28px; text-align: center; }
        .cta a { display: inline-block; background: #059669; color: #fff; text-decoration: none; padding: 12px 28px; border-radius: 6px; font-size: 14px; font-weight: 600; }
        .footer { background: #f9f9f9; padding: 16px 32px; font-size: 12px; color: #999; border-top: 1px solid #e0e0e0; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <h1>⚠ SLA Warning — Action Required Soon</h1>
    </div>
    <div class="body">
        <div class="alert-box">
            <p>
                This document has used approximately <strong>75% of its allowed processing time</strong> in
                <strong>{{ $department->name }}</strong>. Please prioritise it to avoid an SLA breach.
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
                <td>SLA Window</td>
                <td>{{ $department->sla_hours }} hours</td>
            </tr>
            <tr>
                <td>Time Remaining</td>
                <td style="color:#d97706;">~{{ round($department->sla_hours * 0.25) }} hour(s)</td>
            </tr>
        </table>

        <div class="mitigation">
            <h3>Recommended Actions</h3>
            <ul>
                <li>Assign or escalate the document to an available staff member immediately.</li>
                <li>If approval from a supervisor is pending, expedite that request now.</li>
                <li>Scan the document OUT as soon as processing is complete to reset the SLA timer for the next department.</li>
                <li>If additional time is genuinely needed, contact the system administrator to log the reason.</li>
            </ul>
        </div>

        <div class="cta">
            <a href="{{ url('/track/' . $document->tracking_number) }}">View Document Status</a>
        </div>
    </div>
    <div class="footer">
        This is an automated notification from SPeED TraQR. Do not reply to this email.
        Document tracker: {{ url('/track/' . $document->tracking_number) }}
    </div>
</div>
</body>
</html>