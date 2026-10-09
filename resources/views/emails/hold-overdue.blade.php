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
        <h1>⏸ Hold Expired — {{ $document->holdOverdueDays() }} day(s) over</h1>
    </div>
    <div class="body">
        <div class="alert-box">
            @if ($isEscalation)
                <p>
                    This request has been parked <strong>On Hold</strong> past the date it was due to resume, and the
                    hold pauses its SLA clock — so it is <strong>not appearing in the at-risk list</strong>. It needs a
                    decision from a supervisor: resume it, re-date the hold, or close it out.
                </p>
            @else
                <p>
                    A request you parked <strong>On Hold</strong> has passed the date it was meant to resume. Its SLA
                    clock is paused while it is held, so nothing else will flag it. Please resume it, set a new hold
                    date, or close it out.
                </p>
            @endif
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
                <td>Assigned To</td>
                <td>{{ $document->assignedTo?->name ?? 'Unassigned' }}</td>
            </tr>
            <tr>
                <td>Waiting On</td>
                <td>{{ $document->blocked_by ? ucfirst($document->blocked_by) : 'Not recorded' }}</td>
            </tr>
            <tr>
                <td>Held Since</td>
                <td>{{ $document->held_at?->format('M d, Y') ?? 'Unknown' }}</td>
            </tr>
            <tr>
                <td>{{ $document->hold_until ? 'Hold Date Set' : 'Open-ended, stale after' }}</td>
                <td>
                    @if ($document->hold_until)
                        {{ $document->hold_until->format('M d, Y') }}
                    @else
                        {{ config('tracking.holds.stale_after_days') }} days
                    @endif
                </td>
            </tr>
            <tr>
                <td>Days Overdue</td>
                <td style="color:#d97706;">{{ $document->holdOverdueDays() }}</td>
            </tr>
            <tr>
                <td>Stage On Resume</td>
                <td>{{ \App\Enums\DocumentStatus::fromLoose($document->status_before_hold)->label() }}</td>
            </tr>
        </table>

        @if (! empty($document->hold_reason))
            <div class="mitigation">
                <h3>Reason Given</h3>
                <p style="font-size:14px;margin:0;">{{ $document->hold_reason }}</p>
            </div>
        @endif

        <div class="mitigation">
            <h3>What To Do</h3>
            <ul>
                <li><strong>Unblocked?</strong> Lift the hold — the stage resumes where its SLA paused.</li>
                <li><strong>Still waiting?</strong> Re-hold it with a new date so the next deadline is honest.</li>
                <li><strong>Waiting on the citizen?</strong> Message them from the tracking page before the wait grows.</li>
                <li><strong>Cannot proceed at all?</strong> Return it for revision or deny it with a reason, rather than leaving it parked.</li>
            </ul>
        </div>

        <div class="cta">
            <a href="{{ url('/track/' . $document->tracking_number) }}">Open This Request</a>
        </div>
    </div>
    <div class="footer">
        This is an automated notification from SPeED TraQR. Do not reply to this email.
        Document tracker: {{ url('/track/' . $document->tracking_number) }}
    </div>
</div>
</body>
</html>
