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

<tr {{ $attributes->class('border-b border-gray-200/90 transition-colors duration-150 hover:bg-emerald-50/40 last:border-b-0') }}>
    @if($index !== null)
        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500">{{ $index }}</td>
    @endif
    <td class="max-w-[200px] truncate px-4 py-3 text-sm font-medium text-gray-900">{{ $fileName }}</td>
    <td class="whitespace-nowrap px-4 py-3 text-sm font-semibold text-gray-900 font-mono">
        @if($href)
            <a href="{{ $href }}" class="text-emerald-900 underline-offset-2 hover:text-emerald-700 hover:underline">{{ $tracking }}</a>
        @else
            {{ $tracking }}
        @endif
    </td>
    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700">{{ $date }}</td>
    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700">{{ $category }}</td>
    <td class="px-4 py-3"><x-status-badge :status="$status" /></td>
    @if($stickerHref)
        <td class="whitespace-nowrap px-4 py-3 text-right">
            <a href="{{ $stickerHref }}" target="_blank" rel="noopener" class="text-sm font-semibold text-emerald-800 hover:text-emerald-950 hover:underline">Print QR</a>
        </td>
    @endif
</tr>
