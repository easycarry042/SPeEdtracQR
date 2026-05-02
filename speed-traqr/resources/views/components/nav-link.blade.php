@props([
    'href' => '#',
    'label' => '',
    'active' => false,
])

<a href="{{ $href }}"
   {{ $attributes->merge([
       'class' => 'inline-flex h-[52px] items-center border-b-2 px-1 text-[15px] font-semibold transition ' .
           ($active
               ? 'border-[#1a5c1a] text-[#1a5c1a]'
               : 'border-transparent text-[#666666] hover:border-[#2e7d2e] hover:text-[#2e7d2e]')
   ]) }}>
    {{ $label ?: $slot }}
</a>
