@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'w-full border border-gray-300 rounded-lg px-4 py-2.5 text-gray-800 placeholder-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 focus:outline-none shadow-sm transition']) }}>