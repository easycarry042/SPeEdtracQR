<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center px-4 py-2 bg-paper border border-hairline-strong rounded-md font-semibold text-sm text-ink tracking-tight hover:bg-green-wash active:translate-y-px focus:outline-none focus-visible:ring-2 focus-visible:ring-green focus-visible:ring-offset-2 disabled:opacity-50 disabled:pointer-events-none transition duration-150 ease-out']) }}>
    {{ $slot }}
</button>
