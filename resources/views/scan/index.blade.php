<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-4xl font-extrabold text-[#1a5c1a]">Scan Document</h2>
            @if(!$isOrgWide && $dept)
                <p class="mt-1 text-sm text-emerald-700">Scanning as <span class="font-semibold">{{ $dept->name }}</span></p>
            @endif
        </div>
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
                        <select id="department_id" class="w-full rounded-lg border border-gray-300 px-3 py-2 {{ !$isOrgWide ? 'bg-gray-100' : '' }}" @disabled(!$isOrgWide)>
                            @foreach($departments as $department)
                                <option value="{{ $department->id }}" @selected((int)$userDepartmentId === (int)$department->id)>{{ $department->name }}</option>
                            @endforeach
                        </select>
                        @if(!$isOrgWide)
                            <p class="mt-1 text-xs text-gray-500">Department is fixed to your account.</p>
                        @endif
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-gray-700">Action</label>
                        <div class="flex gap-2">
                            <button type="button" class="action-btn flex-1 inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 font-bold transition" data-action="in" aria-pressed="true">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v12m0 0 4.5-4.5M12 16.5 7.5 12M4.5 19.5h15"/></svg>
                                IN
                            </button>
                            <button type="button" class="action-btn flex-1 inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2 font-bold transition" data-action="out" aria-pressed="false">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19.5v-12m0 0L7.5 12M12 7.5l4.5 4.5M4.5 4.5h15"/></svg>
                                OUT
                            </button>
                        </div>
                    </div>
                </div>

                <div class="mt-3">
                    <label class="mb-1 block text-sm font-semibold text-gray-700">Remarks (optional)</label>
                    <input id="remarks" class="w-full rounded-lg border border-gray-300 px-3 py-2" />
                </div>

                <div class="mt-3">
                    <label class="mb-1 block text-sm font-semibold text-gray-700">Photo (optional)</label>
                    <input id="scanAttachment" type="file" accept="image/*"
                           class="w-full text-sm text-gray-600 file:mr-2 file:rounded-lg file:border-0 file:bg-emerald-50 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-emerald-800">
                    <p class="mt-1 text-xs text-gray-500">Attach a photo of the document when scanning (requires internet).</p>
                </div>

                {{-- Next department: shown for OUT scans only --}}
                <div id="nextDeptWrap" class="mt-3 hidden">
                    <label class="mb-1 block text-sm font-semibold text-gray-700">
                        Next Department
                        <span class="ml-1 text-xs font-normal text-gray-400">(required if no routing rule is configured)</span>
                    </label>
                    <select id="next_department_id" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                        <option value="">— Auto (use routing rule) —</option>
                        @foreach($allDepartments as $department)
                            <option value="{{ $department->id }}">{{ $department->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Mark as complete: shown when no routing rule exists --}}
                <div id="completeWrap" class="mt-3 hidden rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <p class="text-sm font-semibold text-amber-800">Is this the final stop?</p>
                    <p class="mt-0.5 text-xs text-amber-700">If no next department applies, you can close this document here.</p>
                    <input type="hidden" id="pendingTrackingNumber" value="">
                    <button id="completeDocBtn"
                            class="mt-3 rounded-lg bg-amber-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-amber-700">
                        Mark as Completed
                    </button>
                </div>

                <div id="reader" class="mt-4 overflow-hidden rounded-lg border border-gray-300"></div>

                <div class="mt-3 flex gap-2">
                    <input id="manualTracking" placeholder="SPD-YYYYMMDD-XXXXXX" class="flex-1 rounded-lg border border-gray-300 px-3 py-2 font-mono uppercase tracking-widest">
                    <button id="manualSubmit" class="rounded-lg bg-[#1a5c1a] px-4 py-2 font-bold text-white">Submit</button>
                </div>
                {{-- Scan result toast --}}
                <div id="result" class="mt-3 hidden">
                    <div id="resultInner" class="flex items-start gap-3 rounded-xl border p-4 text-sm font-semibold shadow-sm">
                        <div id="resultIcon" class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full">
                            <svg id="resultIconSvg" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"></svg>
                        </div>
                        <span id="resultText" class="flex-1 leading-snug"></span>
                    </div>
                </div>
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
        const scanUrl = @json(route('api.scan.store'));
        let action = 'in';
        let queueCount = 0;

        function paintActionButtons() {
            document.querySelectorAll('.action-btn').forEach(b => {
                const isActive = b.dataset.action === action;
                const activeBg = b.dataset.action === 'in' ? 'bg-green-600' : 'bg-red-600';
                const activeRing = b.dataset.action === 'in' ? 'ring-green-700' : 'ring-red-700';
                b.classList.remove('bg-green-600', 'bg-red-600', 'bg-gray-200', 'text-white',
                    'text-gray-600', 'ring-2', 'ring-offset-1', 'ring-green-700', 'ring-red-700');
                if (isActive) {
                    b.classList.add(activeBg, 'text-white', 'ring-2', 'ring-offset-1', activeRing);
                } else {
                    b.classList.add('bg-gray-200', 'text-gray-600');
                }
                b.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            });
            // Show next-department picker only for OUT
            document.getElementById('nextDeptWrap').classList.toggle('hidden', action !== 'out');
        }

        document.querySelectorAll('.action-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                action = btn.dataset.action;
                paintActionButtons();
            });
        });
        paintActionButtons();

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
            const wrap   = document.getElementById('result');
            const inner  = document.getElementById('resultInner');
            const icon   = document.getElementById('resultIcon');
            const svg    = document.getElementById('resultIconSvg');
            const text   = document.getElementById('resultText');

            const styles = {
                success: {
                    inner: 'border-emerald-200 bg-emerald-50 text-emerald-900',
                    icon:  'bg-emerald-100 text-emerald-700',
                    path:  '<path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>',
                },
                warn: {
                    inner: 'border-amber-200 bg-amber-50 text-amber-900',
                    icon:  'bg-amber-100 text-amber-700',
                    path:  '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/>',
                },
                error: {
                    inner: 'border-red-200 bg-red-50 text-red-900',
                    icon:  'bg-red-100 text-red-700',
                    path:  '<path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>',
                },
            };
            const s = styles[type] || styles.error;
            inner.className = `flex items-start gap-3 rounded-xl border p-4 text-sm font-semibold shadow-sm ${s.inner}`;
            icon.className  = `mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full ${s.icon}`;
            svg.innerHTML   = s.path;
            text.innerHTML  = message;
            wrap.classList.remove('hidden');
        }

        async function submitScan(trackingNumber) {
            const nextDeptVal = document.getElementById('next_department_id').value;
            const attachmentInput = document.getElementById('scanAttachment');
            const hasFile = attachmentInput?.files?.length > 0;

            const payload = {
                tracking_number:    trackingNumber,
                department_id:      document.getElementById('department_id').value,
                action,
                remarks:            document.getElementById('remarks').value,
                scanned_at:         new Date().toISOString(),
                offline_uuid:       crypto.randomUUID(),
                next_department_id: (action === 'out' && nextDeptVal) ? nextDeptVal : null,
            };

            if (!navigator.onLine) {
                if (hasFile) {
                    setResult('warn', 'Photos cannot be uploaded offline. Submit without a photo, or wait until you are online.');
                    return;
                }
                await addPending(payload);
                await refreshOfflineBadge();
                setResult('warn', 'Offline detected: scan queued.');
                return;
            }

            let res;
            if (hasFile) {
                const formData = new FormData();
                Object.entries(payload).forEach(([key, value]) => {
                    if (value !== null && value !== '') formData.append(key, value);
                });
                formData.append('attachment', attachmentInput.files[0]);
                res = await fetch(scanUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    body: formData,
                });
            } else {
                res = await fetch(scanUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    body: JSON.stringify(payload),
                });
            }

            const data = await res.json();
            if (res.ok) {
                const next = data.next_department ? ` → Next: <strong>${data.next_department.name}</strong>` : '';
                setResult('success', `${data.message}${next}`);
                document.getElementById('next_department_id').value = '';
                document.getElementById('completeWrap').classList.add('hidden');
                if (attachmentInput) attachmentInput.value = '';
            } else {
                if (data.requires_destination) {
                    setResult('warn', 'No routing rule found. Select the next department below, or mark as completed if this is the final stop.');
                    document.getElementById('nextDeptWrap').classList.remove('hidden');
                    document.getElementById('next_department_id').focus();
                    document.getElementById('completeWrap').classList.remove('hidden');
                    document.getElementById('pendingTrackingNumber').value = trackingNumber;
                } else {
                    setResult('error', data.message || 'Scan failed.');
                }
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

        document.getElementById('completeDocBtn').addEventListener('click', async () => {
            const tracking = document.getElementById('pendingTrackingNumber').value;
            if (! tracking) return;
            const res = await fetch(`/documents/${tracking}/complete`, {
                method: 'PATCH',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            });
            const data = await res.json();
            if (res.ok) {
                setResult('success', data.message);
                document.getElementById('completeWrap').classList.add('hidden');
                document.getElementById('nextDeptWrap').classList.add('hidden');
            } else {
                setResult('error', data.message || 'Failed to complete document.');
            }
        });

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
