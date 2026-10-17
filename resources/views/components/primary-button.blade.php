<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2 bg-green-deep border border-transparent rounded-md font-semibold text-sm text-on-green tracking-tight shadow-sm hover:bg-green hover:shadow active:bg-green-deep active:translate-y-px focus:outline-none focus-visible:ring-2 focus-visible:ring-green focus-visible:ring-offset-2 disabled:opacity-50 disabled:pointer-events-none transition duration-150 ease-out']) }}>
    {{ $slot }}
</button>
