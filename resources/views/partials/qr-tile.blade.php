{{-- The tracking-number block shared by the staff modals (Look Up details and
     the dashboard Review): a QR button beside the number that opens the
     request's generated QR, ready to download or print on its claim slip.

     $record — the Alpine expression holding the request (`selected`, `active`).
     The surrounding scope needs a `qrOpen` flag; the viewer covers the modal
     card it sits in (.dm-card is the positioned ancestor). --}}
<div class="flex items-center gap-3">
    <button type="button" @click="qrOpen = true" class="dm-qr-btn"
            :aria-label="'Show QR code for ' + {{ $record }}?.tracking_number" title="Show QR code">
        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            {{-- Three finder patterns and a scatter of modules: reads as "QR" at a glance. --}}
            <path fill-rule="evenodd" d="M3 3h7v7H3V3Zm2 2v3h3V5H5Zm9-2h7v7h-7V3Zm2 2v3h3V5h-3ZM3 14h7v7H3v-7Zm2 2v3h3v-3H5Z"/>
            <path d="M14 14h2.5v2.5H14zM18.5 14H21v2.5h-2.5zM16.5 16.5H19V19h-2.5zM14 18.5h2.5V21H14zM18.5 18.5H21V21h-2.5z"/>
        </svg>
        <span class="dm-qr-zoom" aria-hidden="true">
            <svg fill="none" stroke="currentColor" stroke-width="2.6" viewBox="0 0 24 24"><circle cx="10.5" cy="10.5" r="6"/><path stroke-linecap="round" d="m15 15 5 5"/></svg>
        </span>
    </button>
    <div>
        <p class="dm-title !text-[length:var(--fs-label)]">Tracking Number</p>
        <p class="mono text-[length:var(--fs-small)] font-bold text-ink" x-text="{{ $record }}?.tracking_number"></p>
    </div>
</div>

{{-- show-/hide-request-qr: the Receive card's "Select the QR on screen" opens
     this viewer for the snipping tool, and closes it again if the snip fails. --}}
<div x-show="qrOpen" x-cloak x-transition.opacity.duration.150ms
     x-on:show-request-qr.window="if ($event.detail === {{ $record }}?.tracking_number) qrOpen = true"
     x-on:hide-request-qr.window="if ($event.detail === {{ $record }}?.tracking_number) qrOpen = false"
     class="dm-qr-layer" @click.self="qrOpen = false"
     role="dialog" aria-modal="true" aria-label="QR code">
    <div class="dm-qr-panel">
        <div class="flex items-center justify-between gap-3">
            <p class="dm-section">QR code</p>
            <button type="button" @click="qrOpen = false" aria-label="Close QR code" class="dm-close">
                <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </div>

        <template x-if="{{ $record }}?.qr_url">
            <div class="dm-qr-frame">
                <img :src="{{ $record }}.qr_url" :alt="'QR code for ' + {{ $record }}.tracking_number">
            </div>
        </template>
        <template x-if="! {{ $record }}?.qr_url">
            <p class="dm-qr-missing">No QR code has been generated for this request.</p>
        </template>

        <p class="mono mt-3 text-center text-[length:var(--fs-body)] font-bold tracking-wide text-ink" x-text="{{ $record }}?.tracking_number"></p>
        {{-- An internal request's QR opens the public internal tracker
             (App\Support\TrackingQr::content()): which office has it and who is
             handling it, no account needed. --}}
        <p class="mt-1 text-center text-[length:var(--fs-caption)] text-ink-soft"
           x-text="({{ $record }}?.tracking_number || '').startsWith('INT-')
               ? 'Any phone camera can scan it to see which office has it — no account needed.'
               : 'Scanning it opens this request\'s public tracking page.'"></p>

        <div class="dm-actions mt-4 justify-center">
            <a x-show="{{ $record }}?.qr_url" :href="{{ $record }}?.qr_url" :download="{{ $record }}?.tracking_number + '.png'" class="cr-btn">
                <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v11m0 0-4-4m4 4 4-4M5 19h14"/></svg>
                Download PNG
            </a>
            <a :href="{{ $record }}?.slip_url" target="_blank" rel="noopener" class="cr-btn cr-btn-primary">
                <svg fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linejoin="round" d="M7 9V4h10v5M7 17H5a1 1 0 0 1-1-1v-5a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v5a1 1 0 0 1-1 1h-2"/><path stroke-linejoin="round" d="M7 14h10v6H7z"/></svg>
                Print slip
            </a>
        </div>
    </div>
</div>
