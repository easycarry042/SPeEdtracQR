<!DOCTYPE html>
<html>
<head>
    <title>SPeED TraQR</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="card shadow-sm">
                    <div class="card-body p-4">
                        <h1 class="h3 mb-3">SPeED TraQR</h1>
                        <p class="text-muted mb-4">Track your document using its tracking number.</p>

                        <form id="trackForm" class="row g-2">
                            <div class="col-sm-9">
                                <input
                                    id="trackingNumber"
                                    type="text"
                                    class="form-control"
                                    placeholder="e.g. SPD-20260425-XXXXXX"
                                    required
                                >
                            </div>
                            <div class="col-sm-3 d-grid">
                                <button type="submit" class="btn btn-primary">Track</button>
                            </div>
                        </form>

                        <div class="mt-3">
                            <a href="{{ route('scanner') }}" class="btn btn-outline-secondary btn-sm">Open Scanner</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('trackForm').addEventListener('submit', function (event) {
            event.preventDefault();
            const trackingNumber = document.getElementById('trackingNumber').value.trim();
            if (!trackingNumber) {
                return;
            }
            window.location.href = '/track/' + encodeURIComponent(trackingNumber);
        });
    </script>
</body>
</html>

