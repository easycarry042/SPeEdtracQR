@props([
    'index' => null,
    'date' => null,
    'tracking' => '',
    'fileName' => '',
    'category' => '',
    'status' => 'pending',
    'href' => null,
    'stickerHref' => null,
])

<tr {{ $attributes }}>
    @if($index !== null)
        <td class="idx mono">{{ $index }}</td>
    @endif
    <td class="nm">{{ $fileName }}</td>
    <td>
        @if($href)
            <a href="{{ $href }}" class="code">{{ $tracking }}</a>
        @else
            <span class="code">{{ $tracking }}</span>
        @endif
    </td>
    <td class="mono muted">{{ $date }}</td>
    <td class="muted">{{ $category }}</td>
    <td><x-status-badge :status="$status" /></td>
    @if($stickerHref)
        <td style="text-align:right">
            <a href="{{ $stickerHref }}" target="_blank" rel="noopener" class="cr-btn cr-btn-ghost cr-btn-sm">
                <svg fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3M21 14M17 21h4v-4M14 21"/></svg>
                Print QR
            </a>
        </td>
    @endif
</tr>
