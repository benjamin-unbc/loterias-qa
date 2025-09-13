@props(['disabled' => false])

<input {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => 'border-gray-300 bg-[#22272b] text-white text-sm focus:border-yellow-200 focus:ring-yellow-200 rounded-md shadow-sm']) !!}>
