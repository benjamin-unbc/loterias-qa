<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2 bg-yellow-200 border border-transparent rounded-md font-semibold text-xs text-gray-600 uppercase tracking-widest hover:bg-yellow-200/90 focus:bg-yellow-200 focus:outline-none focus:ring-2 focus:ring-offset-2 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
