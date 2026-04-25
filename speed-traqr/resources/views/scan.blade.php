<!DOCTYPE html>
<html>
<head>
    <title>QR Scanner - SPeED TraQR</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark text-white">
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card bg-secondary">
                    <div class="card-header">
                        <h4>Scan Document QR Code</h4>
                    </div>
                    <div class="card-body">
                        <div id="reader" style="width:100%"></div>
                        <form id="scanForm" class="mt-3 d-none">
                            <input type="hidden" name="tracking_number" id="trackingNumber">
                            <label>Select Action:</label>
                            <select name="action" id="action" class="form-select mb-2" required>
                                <option value="in">📥 Entering Department</option>
                                <option value="out">📤 Exiting Department</option>
                            </select>
                            <label>Department:</label>
                            <select name="department_id" id="departmentId" class="form-select mb-3" required>
                                @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-primary w-100">Confirm Scan</button>
                        </form>
                        <div id="result" class="mt-3"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        const html5QrCode = new Html5Qrcode("reader");
        let lastScanned = null;

        function startScanner() {
            html5QrCode.start(
                { facingMode: "environment" },
                { fps: 10, qrbox: 250 },
                (decodedText) => {
                    if (lastScanned === decodedText) return;
                    lastScanned = decodedText;
                    // Extract tracking number from URL (format: /track/SPD-...)
                    let trackingNumber = decodedText.split('/').pop();
                    document.getElementById('trackingNumber').value = trackingNumber;
                    document.getElementById('scanForm').classList.remove('d-none');
                    document.getElementById('result').innerHTML = `<div class="alert alert-info">QR Code detected: ${trackingNumber}</div>`;
                },
                (error) => { /* ignore */ }
            ).catch(err => console.error(err));
        }

        startScanner();

        document.getElementById('scanForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const response = await fetch('/api/documents/scan', {
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + localStorage.getItem('token'),
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: formData
            });
            const data = await response.json();
            if (response.ok) {
                document.getElementById('result').innerHTML = `<div class="alert alert-success">Scan recorded! ${data.suggested_next_department ? 'Next department suggested: ID ' + data.suggested_next_department : ''}</div>`;
                setTimeout(() => {
                    document.getElementById('scanForm').classList.add('d-none');
                    lastScanned = null;
                    document.getElementById('result').innerHTML = '';
                }, 2000);
            } else {
                document.getElementById('result').innerHTML = `<div class="alert alert-danger">Error: ${data.message}</div>`;
            }
        });
    </script>
</body>
</html>

