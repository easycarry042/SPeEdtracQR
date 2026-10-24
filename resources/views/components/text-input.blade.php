@props(['disabled' => false])

{{-- Civic Record form field: hairline border, green focus, flat. Matches the
     .field / .gate-panel inputs used elsewhere so every text entry looks alike. --}}
<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-hairline-strong text-ink rounded-md shadow-sm transition duration-150 ease-out focus:border-green focus:ring-green disabled:opacity-50 disabled:bg-green-wash/40']) }}>
