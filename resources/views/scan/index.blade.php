<x-app-layout>
    <x-slot name="header">
        <h2 class="text-4xl font-extrabold text-[#1a5c1a]">Scan Document</h2>
    </x-slot>

    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="mb-6 rounded-2xl border border-emerald-200/80 bg-emerald-50/90 p-4 text-sm text-emerald-950 shadow-sm">
            <p class="font-semibold">How to record a handoff</p>
            <ul class="mt-2 list-inside list-disc space-y-1 text-emerald-900/90">
                <li><strong>IN</strong> — document physically arrived at the department selected above.</li>
                <li><strong>OUT</strong> — document was sent onward; the system moves it to the next department from your <strong>routing rules</strong> (or marks completed if there is no next step).</li>
                <li>Scan the QR on the folder, or type the tracking number, then submit.</li>
            </ul>
        </div>

        <div class="mb-4 flex items-center gap-3">
            <span id="offlineBadge" class="hidden rounded-md bg-yellow-200 px-3 py-1 text-sm font-semibold text-yellow-800">Offline queue: 0</span>
            <button id="syncNowBtn" class="rounded-md bg-[#1a5c1a] px-3 py-1 text-sm font-semibold text-white">Sync Now</button>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div class="rounded-xl border border-[#e0e0e0] bg-white p-5">
                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-gray-700">Department</label>
                        <select id="department_id" class="w-full rounded-lg border border-gray-300 px-3 py-2">
                            @foreach($departments as $department)
                                <option value="{{ $department->id }}" @selected((int)$userDepartmentId === (int)$department->id)>{{ $department->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-gray-700">Action</label>
                        <div class="flex gap-2">
                            <button type="button" class="action-btn flex-1 rounded-lg bg-green-600 px-4 py-2 font-bold text-white" data-action="in">IN</button>
                            <button type="button" class="action-btn flex-1 rounded-lg bg-red-300 px-4 py-2 font-bold text-white" data-action="out">OUT</button>
                        </div>
                    </div>
                </div>

                <div class="mt-3">
                    <label class="mb-1 block text-sm font-semibold text-gray-700">Remarks (optional)</label>
                    <input id="remarks" class="w-full rounded-lg border border-gray-300 px-3 py-2" />
                </div>

                <div id="reader" class="mt-4 overflow-hidden rounded-lg border border-gray-300"></div>

                <div class="mt-3 flex gap-2">
                    <input id="manualTracking" placeholder="SPD-YYYYMMDD-XXXXX" class="flex-1 rounded-lg border border-gray-300 px-3 py-2 font-mono uppercase tracking-widest">
                    <button id="manualSubmit" class="rounded-lg bg-[#1a5c1a] px-4 py-2 font-bold text-white">Submit</button>
                </div>
                <div id="result" class="mt-3 text-sm"></div>
            </div>

            <div class="rounded-xl border border-[#e0e0e0] bg-white p-5">
                <h3 class="text-xl font-bold text-gray-800">Session Scan Log</h3>
                <ul id="sessionLog" class="mt-3 space-y-2">
                    @foreach($sessionScans as $scan)
                        <li class="rounded-md bg-gray-100 px-3 py-2 text-sm">
                            <strong>{{ $scan['tracking_number'] }}</strong> - {{ $scan['action'] }} ({{ $scan['department'] }}) at {{ $scan['at'] }}
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        let action = 'in';
        let queueCount = 0;

        document.querySelectorAll('.action-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                action = btn.dataset.action;
                document.querySelectorAll('.action-btn').forEach(b => {
                    b.classList.remove('bg-green-600', 'bg-red-600');
                    b.classList.add('bg-gray-300');
                });
                if (action === 'in') {
                    btn.classList.remove('bg-gray-300');
                    btn.classList.add('bg-green-600');
                } else {
                    btn.classList.remove('bg-gray-300');
                    btn.classList.add('bg-red-600');
                }
            });
        });

        function normalizeTracking(decodedText) {
            if (decodedText.includes('/track/')) return decodedText.split('/track/').pop();
            return decodedText.trim();
        }

        async function openDb() {
            return await new Promise((resolve, reject) => {
                const req = indexedDB.open('speedtraqr-offline', 1);
                req.onupgradeneeded = () => req.result.createObjectStore('scans', { keyPath: 'offline_uuid' });
                req.onsuccess = () => resolve(req.result);
                req.onerror = () => reject(req.error);
            });
        }

        async function addPending(scan) {
            const db = await openDb();
            const tx = db.transaction('scans', 'readwrite');
            tx.objectStore('scans').put(scan);
            await tx.complete;
        }

        async function getPending() {
            const db = await openDb();
            return await new Promise((resolve, reject) => {
                const tx = db.transaction('scans', 'readonly');
                const req = tx.objectStore('scans').getAll();
                req.onsuccess = () => resolve(req.result || []);
                req.onerror = () => reject(req.error);
            });
        }

        async function clearPending(ids) {
            const db = await openDb();
            const tx = db.transaction('scans', 'readwrite');
            ids.forEach(id => tx.objectStore('scans').delete(id));
        }

        function setResult(type, message) {
            const cls = type === 'success' ? 'bg-green-100 text-green-800' : (type === 'warn' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800');
            document.getElementById('result').innerHTML = `<div class="rounded-md px-3 py-2 ${cls}">${message}</div>`;
        }

        async function submitScan(trackingNumber) {
            const payload = {
                tracking_number: trackingNumber,
                department_id: document.getElementById('department_id').value,
                action,
                remarks: document.getElementById('remarks').value,
                scanned_at: new Date().toISOString(),
                offline_uuid: crypto.randomUUID(),
            };

            if (!navigator.onLine) {
                await addPending(payload);
                await refreshOfflineBadge();
                setResult('warn', 'Offline detected: scan queued.');
                return;
            }

            const res = await fetch('/api/scan', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify(payload),
            });
            const data = await res.json();
            if (res.ok) {
                const next = data.next_department ? ` Next: ${data.next_department.name}` : '';
                setResult('success', `${data.message}${next}`);
            } else {
                setResult('error', data.message || 'Scan failed.');
            }
        }

        async function refreshOfflineBadge() {
            const pending = await getPending();
            queueCount = pending.length;
            const badge = document.getElementById('offlineBadge');
            badge.innerText = `Offline queue: ${queueCount}`;
            badge.classList.toggle('hidden', queueCount === 0);
        }

        async function syncNow() {
            const pending = await getPending();
            if (!pending.length || !navigator.onLine) return;
            const res = await fetch('/api/scan/sync', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({ scans: pending }),
            });
            const data = await res.json();
            if (res.ok) {
                const ids = (data.synced || []).map(item => item.offline_uuid).filter(Boolean);
                await clearPending(ids);
                await refreshOfflineBadge();
            }
        }

        document.getElementById('syncNowBtn').addEventListener('click', syncNow);
        window.addEventListener('online', syncNow);
        document.getElementById('manualSubmit').addEventListener('click', () => {
            const value = document.getElementById('manualTracking').value;
            if (value) submitScan(value.trim());
        });

        const scanner = new Html5Qrcode('reader');
        scanner.start({ facingMode: 'environment' }, { fps: 10, qrbox: 280 }, (decodedText) => {
            const tracking = normalizeTracking(decodedText);
            submitScan(tracking);
        }, () => {});

        refreshOfflineBadge();
    </script>
</x-app-layout>
