<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center px-4 py-2 bg-paper border border-hairline-strong rounded-md font-semibold text-sm text-ink tracking-tight hover:bg-green-wash focus:outline-none focus:ring-2 focus:ring-green focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
