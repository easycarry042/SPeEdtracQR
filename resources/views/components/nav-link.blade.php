@props([
    'href' => '#',
    'label' => '',
    'active' => false,
])

<a href="{{ $href }}"
   {{ $attributes->merge([
       'class' => 'inline-flex h-[52px] items-center border-b-2 px-1 text-[15px] font-semibold transition duration-150 ease-out rounded-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-green focus-visible:ring-offset-2 ' .
           ($active
               ? 'border-green-deep text-green-deep'
               : 'border-transparent text-ink-soft hover:border-green hover:text-green')
   ]) }}>
    {{ $label ?: $slot }}
</a>
