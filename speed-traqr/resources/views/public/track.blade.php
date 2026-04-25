<!DOCTYPE html>
<html>
<head>
    <title>Track Document - SPeED TraQR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="card mx-auto" style="max-width: 700px;">
            <div class="card-header bg-primary text-white">
                <h4>Document Tracking: {{ $document->tracking_number }}</h4>
            </div>
            <div class="card-body">
                <p><strong>Type:</strong> {{ $document->document_type }}</p>
                <p><strong>Citizen:</strong> {{ $document->citizen_name ?? 'N/A' }}</p>
                <p><strong>Status:</strong> 
                    <span class="badge bg-{{ $document->status == 'completed' ? 'success' : 'warning' }}">
                        {{ ucfirst($document->status) }}
                    </span>
                </p>
                <p><strong>Current Location:</strong> 
                    {{ $document->currentDepartment->name ?? 'Not yet assigned' }}
                </p>
                <hr>
                <h5>Routing History</h5>
                <ul class="list-group">
                    @foreach($document->scans as $scan)
                        <li class="list-group-item d-flex justify-content-between">
                            <span>{{ $scan->action == 'in' ? '📥 Entered' : '📤 Exited' }}</span>
                            <span><strong>{{ $scan->department->name }}</strong></span>
                            <span>{{ $scan->scanned_at->format('M d, Y h:i A') }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</body>
</html>