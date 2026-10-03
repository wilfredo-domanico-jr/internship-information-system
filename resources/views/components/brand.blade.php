@props(['variant' => 'default'])
@php $light = $variant === 'light'; @endphp
<a href="{{ url('/') }}" {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5']) }}>
    @if (config('wiis.institution.logo'))
        <img src="{{ asset(config('wiis.institution.logo')) }}" alt="{{ config('wiis.institution.short') }}" class="size-9 rounded-lg object-contain">
    @else
        <span class="grid size-9 place-items-center rounded-lg font-display text-base font-bold {{ $light ? 'bg-white/15 text-white' : 'bg-brand-600 text-white' }}">{{ mb_substr(config('wiis.name'), 0, 1) }}</span>
    @endif
    <span class="leading-tight">
        <span class="block font-display text-lg font-semibold {{ $light ? 'text-white' : 'text-stone-900 dark:text-white' }}">{{ config('wiis.name') }}</span>
        <span class="block text-[11px] uppercase tracking-wider {{ $light ? 'text-brand-200' : 'text-stone-500' }}">{{ config('wiis.institution.short') }}</span>
    </span>
</a>
