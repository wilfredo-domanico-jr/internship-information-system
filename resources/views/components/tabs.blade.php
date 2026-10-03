@props(['tabs' => []])
<nav {{ $attributes->merge(['class' => 'flex gap-1 overflow-x-auto border-b border-stone-200 dark:border-stone-800']) }} aria-label="Tabs">
    @foreach ($tabs as $tab)
        @php $active = request()->routeIs($tab['active'] ?? $tab['route']); @endphp
        <a href="{{ route($tab['route'], $tab['params'] ?? []) }}"
           @class(['-mb-px flex items-center gap-2 whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-medium transition',
                   'border-brand-600 text-brand-700 dark:text-brand-300' => $active,
                   'border-transparent text-stone-500 hover:border-stone-300 hover:text-stone-800 dark:hover:text-stone-200' => ! $active])
           @if ($active) aria-current="page" @endif>
            {{ $tab['label'] }}
            @if (isset($tab['count']) && $tab['count'] !== null)
                <span class="rounded-full bg-stone-100 px-2 py-0.5 text-xs font-semibold text-stone-700 dark:bg-stone-800 dark:text-stone-200">{{ $tab['count'] }}</span>
            @endif
        </a>
    @endforeach
</nav>
