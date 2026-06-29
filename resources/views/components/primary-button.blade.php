<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2 bg-green-deep border border-transparent rounded-md font-semibold text-sm text-on-green tracking-tight hover:bg-green focus:bg-green active:bg-green-deep focus:outline-none focus:ring-2 focus:ring-green focus:ring-offset-2 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
