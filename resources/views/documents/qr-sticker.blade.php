@php
    $trackUrl = $trackingUrl ?? url('/track/'.$document->tracking_number);
    $qrSvg = \SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(200)->margin(0)->errorCorrection('M')->generate($trackUrl);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Sticker — {{ $document->tracking_number }}</title>
    <style>
        @page { size: 62mm 34mm; margin: 1.5mm; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; }
        body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .ticket {
            width: 59mm;
            max-width: 100%;
            border: 0.35mm solid #1a3d1a;
            border-radius: 1.5mm;
            padding: 1.5mm 2mm;
            display: flex;
            flex-direction: column;
            gap: 1mm;
        }
        .hdr {
            font-size: 6.5pt;
            font-weight: 700;
            text-align: center;
            color: #14532d;
            line-height: 1.1;
        }
        .row {
            display: flex;
            align-items: flex-start;
            gap: 2mm;
        }
        .qr-wrap {
            flex: 0 0 19mm;
            width: 19mm;
            height: 19mm;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fff;
        }
        .qr-wrap svg {
            width: 19mm !important;
            height: 19mm !important;
            display: block;
        }
        .meta {
            flex: 1;
            min-width: 0;
            font-size: 6.5pt;
            line-height: 1.25;
            color: #111;
        }
        .tracking {
            font-family: ui-monospace, monospace;
            font-weight: 800;
            font-size: 7.5pt;
            word-break: break-all;
            color: #14532d;
        }
    </style>
</head>
<body onload="window.print()">
    <div class="ticket">
        <div class="hdr">Municipality of San Pedro</div>
        <div class="row">
            <div class="qr-wrap">{!! $qrSvg !!}</div>
            <div class="meta">
                <div class="tracking">{{ $document->tracking_number }}</div>
                <div>{{ $document->document_type }}</div>
                <div>{{ $document->citizen_name ?? 'N/A' }}</div>
            </div>
        </div>
    </div>
</body>
</html>
