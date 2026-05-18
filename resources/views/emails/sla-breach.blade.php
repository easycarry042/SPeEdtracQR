<h2>SLA Breach Alert</h2>
<p>A document is still in your department beyond the configured SLA window.</p>
<ul>
    <li><strong>Tracking Number:</strong> {{ $document->tracking_number }}</li>
    <li><strong>Document Type:</strong> {{ $document->document_type }}</li>
    <li><strong>Citizen Name:</strong> {{ $document->citizen_name ?? 'N/A' }}</li>
    <li><strong>Department:</strong> {{ $department->name }}</li>
    <li><strong>Status:</strong> {{ $document->status }}</li>
</ul>
<p>Please process this document immediately.</p>
