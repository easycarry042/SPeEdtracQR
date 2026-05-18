<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('QR Code Scanner') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="p-6">
                    <div id="reader" class="mx-auto w-full max-w-2xl"></div>

                    <form id="scanForm" class="mt-5 hidden max-w-2xl space-y-3 rounded-lg border border-gray-200 bg-gray-50 p-4">
                        @csrf
                        <input type="hidden" name="tracking_number" id="trackingNumber">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Action</label>
                            <select name="action" id="action" class="block w-full rounded-lg border-gray-300 text-sm" required>
                            <option value="in">📥 Entering Department</option>
                            <option value="out">📤 Exiting Department</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Department</label>
                            <select name="department_id" id="departmentId" class="block w-full rounded-lg border-gray-300 text-sm" required>
                            @foreach($departments as $dept)
                                <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                            @endforeach
                            </select>
                        </div>
                        <button type="submit" class="inline-flex rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700">Confirm Scan</button>
                    </form>

                    <div id="result" class="mt-3"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Include html5-qrcode library -->
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

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
                    let trackingNumber = decodedText.split('/').pop();
                    document.getElementById('trackingNumber').value = trackingNumber;
                    document.getElementById('scanForm').classList.remove('hidden');
                    document.getElementById('result').innerHTML = `<div class="rounded-lg border border-blue-200 bg-blue-50 p-3 text-sm text-blue-700">QR Code detected: ${trackingNumber}</div>`;
                },
                (error) => { /* ignore */ }
            ).catch(err => console.error(err));
        }

        startScanner();

        document.getElementById('scanForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const response = await fetch('{{ route('scanner.scan') }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: formData
            });
            const data = await response.json();
            if (response.ok) {
                document.getElementById('result').innerHTML = `<div class="rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-700">Scan recorded! ${data.suggested_next_department ? 'Next department suggested: ID ' + data.suggested_next_department : ''}</div>`;
                setTimeout(() => {
                    document.getElementById('scanForm').classList.add('hidden');
                    lastScanned = null;
                    document.getElementById('result').innerHTML = '';
                }, 2000);
            } else {
                document.getElementById('result').innerHTML = `<div class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">Error: ${data.message ?? 'Unable to record scan.'}</div>`;
            }
        });
    </script>
</x-app-layout>