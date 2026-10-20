<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; color: #1a1a1a; margin: 0; padding: 0; background: #f5f5f5; }
        .wrapper { max-width: 580px; margin: 32px auto; background: #fff; border-radius: 8px; overflow: hidden; border: 1px solid #e0e0e0; }
        .header { padding: 24px 32px; }
        .header h1 { color: #fff; margin: 0; font-size: 20px; }
        .header-approved { background: #0f4d28; }
        .header-rescheduled { background: #8a5a00; }
        .header-cancelled { background: #8a1f1f; }
        .header-returned { background: #0f4d28; }
        .header-overdue { background: #8a1f1f; }
        .header-extended { background: #0f4d28; }
        .body { padding: 28px 32px; font-size: 14px; line-height: 1.6; }
        .slot { display: inline-block; font-size: 15px; font-weight: 700; color: #0f4d28; background: #eef5f0; border: 1px solid #cfe3d6; border-radius: 6px; padding: 8px 16px; margin: 8px 0 16px; }
        .cancelled-note { color: #8a1f1f; font-weight: 600; }
        .cta { margin-top: 24px; text-align: center; }
        .cta a { display: inline-block; background: #167a3a; color: #fff; text-decoration: none; padding: 12px 28px; border-radius: 6px; font-size: 14px; font-weight: 600; }
        .footer { background: #f9f9f9; padding: 16px 32px; font-size: 12px; color: #999; border-top: 1px solid #e0e0e0; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header header-{{ $outcome }}">
        <h1>@switch($outcome)
            @case('approved') Booking confirmed @break
            @case('rescheduled') Booking rescheduled @break
            @case('cancelled') Booking cancelled @break
            @case('returned') Items received — thank you @break
            @case('overdue') Please return what you borrowed @break
            @case('extended') New return date @break
            @default Booking update
        @endswitch</h1>
    </div>
    <div class="body">
        <p>Hello {{ $citizenName ?? 'there' }},</p>
        @switch($outcome)
            @case('approved')
                <p>Good news — your reservation for <strong>{{ $resourceName }}</strong> has been approved for:</p>
                <div class="slot">{{ $startsAt->format('D, M j, Y · g:i A') }} – {{ $startsAt->isSameDay($endsAt) ? $endsAt->format('g:i A') : $endsAt->format('M j, g:i A') }}</div>
                @break
            @case('rescheduled')
                <p>Your reservation for <strong>{{ $resourceName }}</strong> has been moved to a new time:</p>
                <div class="slot">{{ $startsAt->format('D, M j, Y · g:i A') }} – {{ $startsAt->isSameDay($endsAt) ? $endsAt->format('g:i A') : $endsAt->format('M j, g:i A') }}</div>
                @break
            @case('cancelled')
                <p>Your reservation for <strong>{{ $resourceName }}</strong> scheduled on {{ $startsAt->format('D, M j, Y · g:i A') }} has been <span class="cancelled-note">cancelled</span>.</p>
                <p>If this was unexpected, please contact the office or submit a new request.</p>
                @break
            @case('returned')
                <p>We have received {{ $units ? number_format($units).' ' : '' }}<strong>{{ $resourceName }}</strong> back. Your borrowing is now closed — thank you for returning {{ $units === 1 ? 'it' : 'them' }}.</p>
                @break
            @case('extended')
                <p>The return date for {{ $units ? number_format($outstanding).' ' : '' }}<strong>{{ $resourceName }}</strong> has been changed. Please bring {{ $units === 1 || ! $units ? 'it' : 'them' }} back by:</p>
                <div class="slot">{{ $endsAt->format('D, M j, Y · g:i A') }}</div>
                @break
            @case('overdue')
                <p>The <strong>{{ $units ? number_format($outstanding).' ' : '' }}{{ $resourceName }}</strong> you borrowed {{ $units === 1 || ! $units ? 'was' : 'were' }} due back on:</p>
                <div class="slot">{{ $endsAt->format('D, M j, Y · g:i A') }}</div>
                <p class="cancelled-note">{{ $daysOverdue }} {{ $daysOverdue === 1 ? 'day' : 'days' }} overdue.</p>
                <p>Please bring {{ $units === 1 || ! $units ? 'it' : 'them' }} back to the office as soon as you can, or contact the office if you need more time. Other residents are waiting to borrow {{ $units === 1 || ! $units ? 'it' : 'them' }}.</p>
                @break
        @endswitch
        <div class="cta"><a href="{{ $trackingUrl }}">View details</a></div>
    </div>
    <div class="footer">SPeED TraQR · This is an automated message — please do not reply.</div>
</div>
</body>
</html>
