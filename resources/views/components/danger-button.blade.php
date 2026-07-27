<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2 bg-red-700 border border-transparent rounded-md font-semibold text-sm text-white tracking-tight shadow-sm hover:bg-red-600 hover:shadow active:bg-red-800 active:translate-y-px focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2 disabled:opacity-50 disabled:pointer-events-none transition duration-150 ease-out']) }}>
    {{ $slot }}
</button>
